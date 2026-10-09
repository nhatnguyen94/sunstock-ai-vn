<?php

namespace Tests\Feature\Backend\Controllers;

use App\Backend\Services\SecurityService;
use App\Backend\Services\SystemHealthService;
use App\Models\AiRequest;
use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use ZipArchive;

/**
 * Admin > Sức khỏe hệ thống (scheduler heartbeat, schedule, backups, the alert bell) and Admin > Bảo mật (sign-in history, addresses that
 * look like password guessing, blocked addresses). Real routes and DB; backups live in a temp folder; no external service is touched.
 */
class HealthAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    private string $backupRoot;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Cache::flush();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->backupRoot = sys_get_temp_dir().'/sunstock-backups-'.uniqid();
        File::ensureDirectoryExists($this->backupRoot);
        config(['backup.path' => $this->backupRoot]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupRoot);
        parent::tearDown();
    }

    private function as(string $role): User
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $user = tap(User::factory()->create(), fn ($u) => $u->assignRole($role))->fresh();
        $this->actingAs($user);

        return $user;
    }

    private function fakeBackup(string $day = '2026/10/9'): string
    {
        $dir = $this->backupRoot.'/'.$day;
        File::ensureDirectoryExists($dir);
        $zip = new ZipArchive;
        $zip->open($dir.'/test_db.zip', ZipArchive::CREATE);
        $zip->addFromString('test_db.sql', '-- dump');
        $zip->close();

        return $dir.'/test_db.zip';
    }

    private function failedLogins(string $ip, int $times, ?string $when = null, string $email = 'x@example.com'): void
    {
        foreach (range(1, $times) as $i) {
            LoginAttempt::create(['email' => $email.$i, 'ip' => $ip, 'success' => false, 'door' => 'web', 'created_at' => $when ?? now()]);
        }
    }

    // ── scheduler heartbeat ─────────────────────────────────────────────────

    #[Group('adminHealth')]
    public function test_the_scheduler_state_follows_the_age_of_its_heartbeat(): void
    {
        $health = app(SystemHealthService::class);
        $this->assertSame('off', $health->scheduler()['state']);

        Cache::put(SystemHealthService::HEARTBEAT_KEY, now()->subSeconds(30)->toIso8601String());
        $this->assertSame('ok', $health->scheduler()['state']);

        Cache::put(SystemHealthService::HEARTBEAT_KEY, now()->subMinutes(6)->toIso8601String());
        $this->assertSame('warn', $health->scheduler()['state']);

        Cache::put(SystemHealthService::HEARTBEAT_KEY, now()->subMinutes(30)->toIso8601String());
        $this->assertSame('bad', $health->scheduler()['state']);
    }

    #[Group('adminHealth')]
    public function test_the_heartbeat_is_really_scheduled_every_minute(): void
    {
        $beat = collect(app(Schedule::class)->events())->first(fn ($e) => $e->description === 'scheduler-heartbeat');

        $this->assertNotNull($beat);
        $this->assertSame('* * * * *', $beat->expression);
    }

    // ── the page ────────────────────────────────────────────────────────────

    #[Group('adminHealth')]
    public function test_the_page_shows_the_scheduler_the_schedule_and_the_disk(): void
    {
        $this->as(Role::ADMIN);
        Cache::put(SystemHealthService::HEARTBEAT_KEY, now()->toIso8601String());

        $this->get('/admin/health')->assertOk()
            ->assertSee('Sức khỏe hệ thống')
            ->assertSee('Đang chạy')
            ->assertSee('sync:news')
            ->assertSee('Ổ đĩa còn trống');
    }

    #[Group('adminHealth')]
    public function test_a_plain_user_cannot_open_the_page(): void
    {
        $this->as(Role::USER);

        $this->get('/admin/health')->assertRedirect();
    }

    // ── backups ─────────────────────────────────────────────────────────────

    #[Group('adminHealth')]
    public function test_backups_found_on_disk_are_listed_and_only_the_admin_can_download_one(): void
    {
        $this->fakeBackup();
        $this->as(Role::ADMIN);
        $id = app(SystemHealthService::class)->backups()[0]['id'];

        $this->get('/admin/health')->assertOk()->assertSee('test_db.zip')->assertSee('Tải');
        $this->get("/admin/health/backup/{$id}")->assertOk()->assertDownload('test_db.zip');

        $this->as(Role::ADMIN_SUPPORT);
        $this->get('/admin/health')->assertOk()->assertDontSee(route('admin.health.backup.download', $id), false);
        $this->get("/admin/health/backup/{$id}")->assertForbidden();
        $this->post('/admin/health/backup')->assertForbidden();
    }

    #[Group('adminHealth')]
    public function test_a_backup_id_that_was_not_found_by_the_scan_is_a_404_and_nothing_outside_the_folder_is_served(): void
    {
        $this->as(Role::ADMIN);

        $this->get('/admin/health/backup/'.str_repeat('a', 24))->assertNotFound();
        $this->get('/admin/health/backup/..%2F..%2F.env')->assertNotFound();
        $this->get('/admin/health/backup/not-hex')->assertNotFound();
    }

    #[Group('adminHealth')]
    public function test_taking_a_backup_is_queued_not_run_inside_the_request_and_audited(): void
    {
        $admin = $this->as(Role::ADMIN);

        $this->post('/admin/health/backup')->assertRedirect();

        Queue::assertPushed(QueuedCommand::class);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'description' => 'Yêu cầu tạo bản sao lưu database']);
    }

    // ── the alert bell ──────────────────────────────────────────────────────

    #[Group('adminHealth')]
    public function test_the_bell_warns_about_a_scheduler_that_never_beat_and_a_missing_backup(): void
    {
        $this->as(Role::ADMIN);

        $this->get('/admin')->assertOk()->assertSee('Chưa thấy nhịp tim của scheduler')->assertSee('Chưa có bản sao lưu database');
    }

    #[Group('adminHealth')]
    public function test_a_healthy_system_shows_the_all_clear(): void
    {
        $this->fakeBackup();
        touch($this->backupRoot.'/2026/10/9/test_db.zip', time());
        Cache::put(SystemHealthService::HEARTBEAT_KEY, now()->toIso8601String());
        $this->as(Role::ADMIN);

        $html = $this->get('/admin')->assertOk()->getContent();

        $this->assertStringNotContainsString('Chưa thấy nhịp tim', $html);
        $this->assertStringNotContainsString('Chưa có bản sao lưu', $html);
    }

    #[Group('adminHealth')]
    public function test_a_failing_ai_raises_an_alert(): void
    {
        foreach (range(1, 6) as $i) {
            AiRequest::create(['user_id' => null, 'kind' => 'chat', 'status' => 'error', 'duration_ms' => 10, 'created_at' => now()]);
        }
        AiRequest::create(['user_id' => null, 'kind' => 'chat', 'status' => 'ok', 'duration_ms' => 10, 'created_at' => now()]);
        $this->as(Role::ADMIN);

        $this->get('/admin')->assertOk()->assertSee('AI lỗi nhiều');
    }

    #[Group('adminHealth')]
    public function test_each_role_sees_only_the_alerts_it_may_open(): void
    {
        $this->failedLogins('198.51.100.9', 12);

        $this->as(Role::ADMIN);
        $this->get('/admin')->assertOk()->assertSee('địa chỉ IP nghi dò mật khẩu');

        Cache::flush();
        $this->as(Role::ADMIN_SUPPORT);   // manage-features but not manage-users
        $this->get('/admin')->assertOk()->assertSee('Chưa thấy nhịp tim của scheduler')->assertDontSee('địa chỉ IP nghi dò mật khẩu');
    }

    // ── sign-in history ─────────────────────────────────────────────────────

    #[Group('adminSecurity')]
    public function test_a_failed_and_a_successful_sign_in_are_both_recorded_without_the_password(): void
    {
        $user = User::factory()->create(['email' => 'real@example.com', 'password' => Hash::make('Passw0rd!x')]);

        $this->post('/login', ['email' => ' Real@Example.com ', 'password' => 'wrong-password'])->assertSessionHasErrors();
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'whatever1A!'])->assertSessionHasErrors();
        $this->post('/login', ['email' => 'real@example.com', 'password' => 'Passw0rd!x']);

        $rows = LoginAttempt::orderBy('id')->get();
        $this->assertSame([false, false, true], $rows->pluck('success')->all());
        $this->assertSame(['real@example.com', 'nobody@example.com', 'real@example.com'], $rows->pluck('email')->all(), 'lower-cased and trimmed');
        $this->assertSame($user->id, $rows[2]->user_id);
        $this->assertSame(['web', 'web', 'web'], $rows->pluck('door')->all());
        $this->assertNotNull($rows[0]->ip);
        $this->assertStringNotContainsString('wrong-password', json_encode($rows->toArray()), 'a password is never stored');
        $this->assertStringNotContainsString('Passw0rd', json_encode($rows->toArray()));
    }

    #[Group('adminSecurity')]
    public function test_the_admin_door_is_recorded_as_admin(): void
    {
        $this->post('/admin/login', ['email' => 'nobody@example.com', 'password' => 'whatever1A!']);

        $this->assertSame('admin', LoginAttempt::sole()->door);
    }

    #[Group('adminSecurity')]
    public function test_the_prune_command_keeps_only_the_last_ninety_days(): void
    {
        $this->failedLogins('198.51.100.1', 1, now()->subDays(100)->toDateTimeString());
        $this->failedLogins('198.51.100.2', 1, now()->subDays(10)->toDateTimeString());

        $this->artisan('logins:prune')->assertExitCode(0);

        $this->assertSame(['198.51.100.2'], LoginAttempt::pluck('ip')->all());
    }

    // ── suspicious addresses ────────────────────────────────────────────────

    #[Group('adminSecurity')]
    public function test_an_address_with_ten_failures_in_an_hour_is_suspicious_and_nine_is_not(): void
    {
        $this->failedLogins('198.51.100.10', 10);
        $this->failedLogins('198.51.100.11', 9);
        $this->failedLogins('198.51.100.12', 15, now()->subHours(3)->toDateTimeString());   // too long ago
        $this->as(Role::ADMIN);

        $this->get('/admin/security')->assertOk()->assertSee('IP nghi dò mật khẩu');
        $this->assertSame(['198.51.100.10'], app(SecurityService::class)->suspiciousIps()->pluck('ip')->all());
    }

    #[Group('adminSecurity')]
    public function test_a_blocked_address_is_no_longer_listed_as_suspicious(): void
    {
        $this->failedLogins('198.51.100.10', 12);
        BlockedIp::create(['ip' => '198.51.100.10', 'created_at' => now()]);
        $this->as(Role::ADMIN);

        $this->get('/admin/security')->assertOk()->assertSee('Không có địa chỉ nào đáng ngờ');
    }

    #[Group('adminSecurity')]
    public function test_the_history_can_be_filtered_to_failures(): void
    {
        LoginAttempt::create(['email' => 'ok@example.com', 'ip' => '198.51.100.20', 'success' => true, 'door' => 'web', 'created_at' => now()]);
        LoginAttempt::create(['email' => 'bad@example.com', 'ip' => '198.51.100.21', 'success' => false, 'door' => 'web', 'created_at' => now()]);
        $this->as(Role::ADMIN);

        $this->get('/admin/security')->assertSee('ok@example.com')->assertSee('bad@example.com');
        $this->get('/admin/security?failed=1')->assertDontSee('ok@example.com')->assertSee('bad@example.com');
    }

    // ── blocking ────────────────────────────────────────────────────────────

    #[Group('adminSecurity')]
    public function test_blocking_an_address_turns_its_requests_away_and_unblocking_lets_them_back(): void
    {
        $admin = $this->as(Role::ADMIN);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])->post('/admin/security/blocked-ips', ['ip' => '198.51.100.30', 'reason' => 'dò mật khẩu'])->assertRedirect();
        $row = BlockedIp::sole();
        $this->assertSame([$admin->id, 'dò mật khẩu'], [$row->blocked_by, $row->reason]);

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.30'])->get('/login')->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.31'])->get('/login')->assertOk();

        $this->as(Role::ADMIN);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])->delete("/admin/security/blocked-ips/{$row->id}")->assertRedirect();

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.30'])->get('/login')->assertOk();
        $this->assertDatabaseHas('activity_logs', ['description' => 'Chặn IP 198.51.100.30']);
        $this->assertDatabaseHas('activity_logs', ['description' => 'Bỏ chặn IP 198.51.100.30']);
    }

    #[Group('adminSecurity')]
    public function test_unsafe_addresses_cannot_be_blocked(): void
    {
        $this->as(Role::ADMIN);
        $me = '203.0.113.50';

        foreach (['not-an-ip', '999.1.1.1', '127.0.0.1', '10.0.0.5', '192.168.1.10', '172.18.0.4', $me] as $bad) {
            $this->withServerVariables(['REMOTE_ADDR' => $me])->post('/admin/security/blocked-ips', ['ip' => $bad])->assertSessionHas('error');
        }
        $this->assertSame(0, BlockedIp::count());
    }

    #[Group('adminSecurity')]
    public function test_an_address_cannot_be_blocked_twice(): void
    {
        $this->as(Role::ADMIN);
        $post = fn () => $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])->post('/admin/security/blocked-ips', ['ip' => '198.51.100.40']);

        $post()->assertSessionHas('success');
        $post()->assertSessionHas('error');
        $this->assertSame(1, BlockedIp::count());
    }

    // ── who may do what ─────────────────────────────────────────────────────

    #[Group('adminSecurity')]
    public function test_a_role_with_manage_users_can_read_the_page_but_not_change_anything(): void
    {
        $role = Role::firstWhere('name', Role::WEBADMIN);
        $role->permissions()->attach(Permission::firstWhere('name', 'manage-users')->id);
        $this->as(Role::WEBADMIN);
        $row = BlockedIp::create(['ip' => '198.51.100.50', 'created_at' => now()]);

        $this->get('/admin/security')->assertOk()->assertDontSee('Bỏ chặn');
        $this->post('/admin/security/blocked-ips', ['ip' => '198.51.100.51'])->assertForbidden();
        $this->delete("/admin/security/blocked-ips/{$row->id}")->assertForbidden();
        $this->assertSame(1, BlockedIp::count());
    }

    #[Group('adminSecurity')]
    public function test_support_and_plain_users_cannot_open_the_page(): void
    {
        $this->as(Role::ADMIN_SUPPORT);
        $this->get('/admin/security')->assertForbidden();

        $this->as(Role::USER);
        $this->get('/admin/security')->assertRedirect();
    }

    #[Group('adminSecurity')]
    public function test_the_menu_links_the_new_pages_for_an_admin(): void
    {
        $this->as(Role::ADMIN);

        $this->get('/admin')->assertOk()->assertSee(route('admin.security.index'), false)->assertSee(route('admin.health'), false);
    }
}
