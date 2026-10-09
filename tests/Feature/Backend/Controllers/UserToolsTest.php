<?php

namespace Tests\Feature\Backend\Controllers;

use App\Backend\Services\UserBulkService;
use App\Models\ActivityLog;
use App\Models\AiRequest;
use App\Models\LoginAttempt;
use App\Models\Permission;
use App\Models\Portfolio;
use App\Models\PortfolioItem;
use App\Models\Role;
use App\Models\User;
use App\Models\WatchlistItem;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Admin > Users: the richer detail page, bulk changes (each account through AdminGuard) and the CSV export (admin only,
 * spreadsheet-safe). Real routes and DB.
 */
class UserToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function as(string $role): User
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $user = tap(User::factory()->create(), fn ($u) => $u->assignRole($role))->fresh();
        $this->actingAs($user);

        return $user;
    }

    // ── detail page ─────────────────────────────────────────────────────────

    #[Group('adminUserTools')]
    public function test_the_detail_page_shows_sign_ins_watchlist_holdings_ai_use_and_activity(): void
    {
        $target = User::factory()->create(['email' => 'target@example.com']);
        LoginAttempt::create(['user_id' => $target->id, 'email' => $target->email, 'ip' => '198.51.100.77', 'success' => true, 'door' => 'web', 'created_at' => now()->subHour()]);
        LoginAttempt::create(['email' => $target->email, 'ip' => '198.51.100.88', 'success' => false, 'door' => 'web', 'created_at' => now()->subHour()]);
        WatchlistItem::create(['user_id' => $target->id, 'symbol' => 'FPT']);
        $pf = Portfolio::create(['user_id' => $target->id, 'name' => 'P', 'total_invested' => 1, 'current_value' => 1, 'is_active' => true]);
        PortfolioItem::create(['portfolio_id' => $pf->id, 'stock_symbol' => 'HPG', 'stock_name' => 'HPG', 'quantity' => 1, 'buy_price' => 1, 'current_price' => 1, 'buy_date' => now()->toDateString()]);
        AiRequest::create(['user_id' => $target->id, 'kind' => 'chat', 'status' => 'ok', 'question' => 'Hỏi thử về VCB', 'duration_ms' => 5, 'created_at' => now()]);
        ActivityLog::create(['user_id' => $target->id, 'user_name' => 'T', 'event_type' => 'login', 'description' => 'Đăng nhập thử nghiệm', 'created_at' => now()]);
        $this->as(Role::ADMIN);

        $this->get("/admin/users/{$target->id}")->assertOk()
            ->assertSee('198.51.100.77')
            ->assertSee('1 lần nhập sai')
            ->assertSee('FPT')
            ->assertSee('HPG')
            ->assertSee('Hỏi thử về VCB')
            ->assertSee('Đăng nhập thử nghiệm');
    }

    #[Group('adminUserTools')]
    public function test_the_detail_page_copes_with_an_account_that_has_nothing(): void
    {
        $target = User::factory()->create();
        $this->as(Role::ADMIN);

        $this->get("/admin/users/{$target->id}")->assertOk()->assertSee('chưa có dữ liệu')->assertSee('Chưa theo dõi mã nào')->assertSee('Chưa hỏi AI câu nào');
    }

    // ── bulk ────────────────────────────────────────────────────────────────

    #[Group('adminUserTools')]
    public function test_bulk_verify_activates_pending_accounts_and_confirms_their_address(): void
    {
        $a = User::factory()->unverified()->create();
        $b = User::factory()->unverified()->create();
        $admin = $this->as(Role::ADMIN);

        $this->post('/admin/users/bulk', ['action' => 'verify', 'ids' => [$a->id, $b->id]])->assertSessionHas('success');

        foreach ([$a, $b] as $u) {
            $this->assertSame(User::STATUS_ACTIVE, $u->fresh()->status);
            $this->assertNotNull($u->fresh()->email_verified_at);
        }
        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'event_type' => 'admin_action']);
    }

    #[Group('adminUserTools')]
    public function test_bulk_block_and_unblock(): void
    {
        $users = User::factory()->count(3)->create();
        $this->as(Role::ADMIN);

        $this->post('/admin/users/bulk', ['action' => 'block', 'ids' => $users->pluck('id')->all()])->assertSessionHas('success');
        $this->assertSame([User::STATUS_BLOCKED], User::whereIn('id', $users->pluck('id'))->pluck('status')->unique()->all());

        $this->post('/admin/users/bulk', ['action' => 'unblock', 'ids' => $users->pluck('id')->all()])->assertSessionHas('success');
        $this->assertSame([User::STATUS_ACTIVE], User::whereIn('id', $users->pluck('id'))->pluck('status')->unique()->all());
    }

    #[Group('adminUserTools')]
    public function test_bulk_ai_block_sets_and_clears_the_timestamp_without_touching_the_status(): void
    {
        $user = User::factory()->create();
        $this->as(Role::ADMIN);

        $this->post('/admin/users/bulk', ['action' => 'ai-block', 'ids' => [$user->id]]);
        $this->assertNotNull($user->fresh()->ai_blocked_at);
        $this->assertSame(User::STATUS_ACTIVE, $user->fresh()->status);

        $this->post('/admin/users/bulk', ['action' => 'ai-unblock', 'ids' => [$user->id]]);
        $this->assertNull($user->fresh()->ai_blocked_at);
    }

    #[Group('adminUserTools')]
    public function test_an_account_the_rules_refuse_is_skipped_and_the_others_are_still_changed(): void
    {
        $other = User::factory()->create();
        $me = $this->as(Role::ADMIN);

        $response = $this->post('/admin/users/bulk', ['action' => 'block', 'ids' => [$me->id, $other->id]]);

        $this->assertSame(User::STATUS_ACTIVE, $me->fresh()->status, 'you cannot block yourself');
        $this->assertSame(User::STATUS_BLOCKED, $other->fresh()->status);
        $response->assertSessionHas('success', fn ($m) => str_contains($m, 'Bỏ qua 1') && str_contains($m, 'tự khoá'));
    }

    #[Group('adminUserTools')]
    public function test_the_last_effective_admin_cannot_be_blocked_in_bulk(): void
    {
        $this->as(Role::ADMIN);
        $second = tap(User::factory()->create(), fn ($u) => $u->assignRole(Role::ADMIN))->fresh();
        User::query()->where('id', '!=', $second->id)->get()->each(fn ($u) => $u->hasRole(Role::ADMIN) ? $u->forceFill(['status' => User::STATUS_INACTIVE])->save() : null);
        // the actor was just made inactive too: act as the one remaining admin
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($second);
        $victim = tap(User::factory()->create(), fn ($u) => $u->assignRole(Role::ADMIN))->fresh();
        $victim->forceFill(['status' => User::STATUS_ACTIVE])->save();

        // two effective admins now: blocking the victim is allowed; blocking the actor (last standing after that) is not
        $this->post('/admin/users/bulk', ['action' => 'block', 'ids' => [$victim->id]])->assertSessionHas('success');
        $this->post('/admin/users/bulk', ['action' => 'block', 'ids' => [$second->id]])->assertSessionHas('error');
        $this->assertSame(User::STATUS_ACTIVE, $second->fresh()->status);
    }

    #[Group('adminUserTools')]
    public function test_bulk_input_is_validated(): void
    {
        $user = User::factory()->create();
        $this->as(Role::ADMIN);

        $this->post('/admin/users/bulk', ['action' => 'verify'])->assertSessionHasErrors('ids');
        $this->post('/admin/users/bulk', ['action' => 'delete-everything', 'ids' => [$user->id]])->assertSessionHasErrors('action');
        $this->post('/admin/users/bulk', ['action' => 'block', 'ids' => ['abc']])->assertSessionHasErrors('ids.0');
        $this->post('/admin/users/bulk', ['action' => 'block', 'ids' => range(1, UserBulkService::MAX_IDS + 1)])->assertSessionHasErrors('ids');
        $this->assertSame(User::STATUS_ACTIVE, $user->fresh()->status);

        $this->post('/admin/users/bulk', ['action' => 'block', 'ids' => [999999]])->assertSessionHas('error');   // nobody matched: nothing done
    }

    #[Group('adminUserTools')]
    public function test_only_the_admin_role_can_use_bulk_even_with_manage_users_delegated(): void
    {
        Role::firstWhere('name', Role::WEBADMIN)->permissions()->attach(Permission::firstWhere('name', 'manage-users')->id);
        $user = User::factory()->create();
        $this->as(Role::WEBADMIN);

        $this->post('/admin/users/bulk', ['action' => 'block', 'ids' => [$user->id]])->assertForbidden();
        $this->assertSame(User::STATUS_ACTIVE, $user->fresh()->status);

        $this->as(Role::USER);
        $this->post('/admin/users/bulk', ['action' => 'block', 'ids' => [$user->id]])->assertRedirect();
        $this->assertSame(User::STATUS_ACTIVE, $user->fresh()->status);
    }

    #[Group('adminUserTools')]
    public function test_the_list_shows_checkboxes_and_the_export_button_to_the_admin_only(): void
    {
        $this->as(Role::ADMIN);
        $this->get('/admin/users')->assertOk()->assertSee('bulkForm', false)->assertSee('Xuất CSV');

        Role::firstWhere('name', Role::WEBADMIN)->permissions()->attach(Permission::firstWhere('name', 'manage-users')->id);
        $this->as(Role::WEBADMIN);
        $this->get('/admin/users')->assertOk()->assertDontSee('bulkForm', false)->assertDontSee('Xuất CSV');
    }

    // ── export ──────────────────────────────────────────────────────────────

    private function csv(string $query = ''): array
    {
        $body = $this->get('/admin/users/export'.$query)->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body, 'UTF-8 BOM so Excel reads Vietnamese');

        return array_map('str_getcsv', array_filter(explode("\n", substr($body, 3))));
    }

    #[Group('adminUserTools')]
    public function test_the_export_has_a_header_and_one_row_per_account(): void
    {
        User::factory()->create(['name' => 'Nguyễn Văn A', 'email' => 'a@example.com']);
        $this->as(Role::ADMIN);

        $rows = $this->csv();

        $this->assertSame(['ID', 'Tên', 'E-mail', 'Trạng thái', 'Vai trò', 'Xác thực e-mail lúc', 'Ngày tạo'], $rows[0]);
        $this->assertCount(User::count() + 1, $rows);
        $this->assertContains('Nguyễn Văn A', array_column($rows, 1));
    }

    #[Group('adminUserTools')]
    public function test_the_export_follows_the_list_filters_including_the_inactive_status(): void
    {
        User::factory()->create(['email' => 'findme@example.com']);
        User::factory()->inactive()->create(['email' => 'off@example.com']);
        $this->as(Role::ADMIN);

        $this->assertSame(['findme@example.com'], array_column(array_slice($this->csv('?search=findme'), 1), 2));
        $this->assertSame(['off@example.com'], array_column(array_slice($this->csv('?status=0'), 1), 2), 'status 0 is a real filter');
    }

    #[Group('adminUserTools')]
    public function test_a_name_that_starts_like_a_formula_is_neutralised(): void
    {
        User::factory()->create(['name' => 'Evil', 'email' => 'e@example.com']);
        User::where('email', 'e@example.com')->update(['name' => '=HYPERLINK("http://evil","x")']);
        $this->as(Role::ADMIN);

        $names = array_column($this->csv('?search=e@example.com'), 1);

        $this->assertContains("'=HYPERLINK(\"http://evil\",\"x\")", $names);
        $this->assertSame("'=1+1", UserBulkService::safeCell('=1+1'));
        $this->assertSame("'+84", UserBulkService::safeCell('+84'));
        $this->assertSame("'-2", UserBulkService::safeCell('-2'));
        $this->assertSame("'@SUM", UserBulkService::safeCell('@SUM'));
        $this->assertSame('Bình thường', UserBulkService::safeCell('Bình thường'));
        $this->assertSame('', UserBulkService::safeCell(''));
    }

    #[Group('adminUserTools')]
    public function test_the_export_is_admin_only_even_for_a_delegated_reader_and_is_audited(): void
    {
        Role::firstWhere('name', Role::WEBADMIN)->permissions()->attach(Permission::firstWhere('name', 'manage-users')->id);
        $this->as(Role::WEBADMIN);
        $this->get('/admin/users/export')->assertForbidden();

        $this->as(Role::USER);
        $this->get('/admin/users/export')->assertRedirect();

        $admin = $this->as(Role::ADMIN);
        $this->get('/admin/users/export')->assertOk();
        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'description' => 'Xuất danh sách user ra CSV']);
    }

    #[Group('adminUserTools')]
    public function test_export_is_not_mistaken_for_a_user_id(): void
    {
        $this->as(Role::ADMIN);

        $this->get('/admin/users/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
