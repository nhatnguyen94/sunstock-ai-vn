<?php

namespace Tests\Feature\Seeders;

use App\Backend\Services\DataQualityService;
use App\Backend\Services\SecurityService;
use App\Models\AiRequest;
use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Models\Role;
use App\Models\Stock;
use App\Models\SyncRun;
use App\Models\User;
use App\Support\SiteSettings;
use Database\Seeders\DemoAdminFeaturesCleanupSeeder;
use Database\Seeders\DemoAdminFeaturesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * The seeder that fills the admin features with data to try by hand (and the one that removes it again): it must produce what each screen
 * looks for, run twice without duplicating anything, and leave nothing behind when cleaned up.
 */
class DemoAdminFeaturesSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Cache::flush();
    }

    #[Group('demoAdminSeeder')]
    public function test_it_creates_what_every_admin_screen_needs(): void
    {
        $this->seed(DemoAdminFeaturesSeeder::class);

        $this->assertTrue(User::firstWhere('email', 'admin@sunstock.test')->hasRole(Role::ADMIN));
        $this->assertSame(24, count(DemoAdminFeaturesSeeder::demoUserIds()));
        $this->assertGreaterThan(0, SyncRun::where('ok', false)->count(), 'a failed run to look at');
        $this->assertGreaterThan(0, SyncRun::where('ok', true)->count());
        foreach (['ok', 'error', 'refused'] as $status) {
            $this->assertGreaterThan(0, AiRequest::where('status', $status)->count(), "AI calls with status {$status}");
        }
        $this->assertGreaterThan(1, AiRequest::whereNotNull('model')->distinct()->count('model'), 'more than one model answered');
        $this->assertSame(1, User::whereNotNull('ai_blocked_at')->count(), 'one account blocked from the AI');
        $this->assertSame(['enabled' => true, 'daily_limit' => 20], SiteSettings::ai());
        $this->assertSame([DemoAdminFeaturesSeeder::BLOCKED_IP], BlockedIp::pluck('ip')->all());
        $this->assertNull(SiteSettings::activeAnnouncement(), 'the notice is prepared but OFF');
        $this->assertNotSame('', SiteSettings::announcement()['text']);
    }

    #[Group('demoAdminSeeder')]
    public function test_the_security_page_finds_the_password_guesser_and_not_the_clumsy_member(): void
    {
        $this->seed(DemoAdminFeaturesSeeder::class);

        $suspicious = app(SecurityService::class)->suspiciousIps();

        $this->assertSame(['198.51.100.41'], $suspicious->pluck('ip')->all());
        $this->assertGreaterThanOrEqual(SecurityService::SUSPICIOUS_FAILURES, $suspicious->first()->failures);
    }

    #[Group('demoAdminSeeder')]
    public function test_the_data_quality_report_finds_one_of_each_problem(): void
    {
        $this->seed(DemoAdminFeaturesSeeder::class);

        $checks = collect(app(DataQualityService::class)->report()['checks'])->keyBy('key');

        $this->assertContains('DEMONOPX', $checks['no-prices']['sample']);
        $this->assertStringContainsString('DEMOOLD', implode(' ', $checks['stale']['sample']));
        $this->assertSame(2, $checks['invalid']['count']);
        $this->assertContains('DEMOGHOST', $checks['unknown-symbols']['sample']);
    }

    #[Group('demoAdminSeeder')]
    public function test_running_it_twice_changes_nothing(): void
    {
        $this->seed(DemoAdminFeaturesSeeder::class);
        $counts = fn () => [AiRequest::count(), LoginAttempt::count(), SyncRun::count(), User::count(), Stock::count(), BlockedIp::count()];
        $first = $counts();

        $this->seed(DemoAdminFeaturesSeeder::class);

        $this->assertSame($first, $counts());
    }

    #[Group('demoAdminSeeder')]
    public function test_the_cleanup_removes_it_all_and_only_it(): void
    {
        $keep = User::factory()->create(['email' => 'someone@example.com']);
        $this->seed(DemoAdminFeaturesSeeder::class);

        $this->seed(DemoAdminFeaturesCleanupSeeder::class);

        $this->assertSame([], DemoAdminFeaturesSeeder::demoUserIds());
        $this->assertSame(0, AiRequest::count());
        $this->assertSame(0, LoginAttempt::where('user_agent', 'DemoSeed/1.0')->count());
        $this->assertSame(0, SyncRun::where('output', DemoAdminFeaturesSeeder::MARK)->count());
        $this->assertSame(0, BlockedIp::count());
        $this->assertSame(0, Stock::whereIn('symbol', ['DEMONOPX', 'DEMOOLD', 'DEMOBAD'])->count());
        $this->assertSame(['enabled' => true, 'daily_limit' => 0], SiteSettings::ai(), 'back to the defaults');
        $this->assertNotNull(User::find($keep->id), 'other accounts are not touched');
    }

    #[Group('demoAdminSeeder')]
    public function test_it_refuses_to_run_outside_local_and_testing(): void
    {
        $this->app['env'] = 'production';

        $this->app->make(DemoAdminFeaturesSeeder::class)->run();   // db:seed itself would ask for --force in production

        $this->assertSame(0, User::count(), 'nothing was created in production');
    }
}
