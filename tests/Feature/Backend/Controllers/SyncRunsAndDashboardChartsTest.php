<?php

namespace Tests\Feature\Backend\Controllers;

use App\Backend\Services\DashboardInsightsService;
use App\Frontend\Services\StockSignalService;
use App\Frontend\Services\WorldMarketService;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Portfolio;
use App\Models\PortfolioItem;
use App\Models\Role;
use App\Models\Stock;
use App\Models\SyncRun;
use App\Models\User;
use App\Models\WatchlistItem;
use App\Support\SyncRunRecorder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

/**
 * Admin > Sync Status (run history, schedule-aware "late", the world-markets and signals sources) and the dashboard charts.
 * Real routes and DB; the sources that live in the cache are seeded by hand, Python is never involved.
 */
class SyncRunsAndDashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Cache::flush();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function actingAsRole(string $role): User
    {
        $user = tap(User::factory()->create(), fn ($u) => $u->assignRole($role))->fresh();
        $this->actingAs($user);

        return $user;
    }

    private function runCommand(string $name, int $exit): void
    {
        $input = new ArrayInput([]);
        $output = new NullOutput;
        event(new CommandStarting($name, $input, $output));
        event(new CommandFinished($name, $input, $output, $exit));
    }

    // ── run history ─────────────────────────────────────────────────────────

    #[Group('adminSyncRuns')]
    public function test_every_finished_sync_command_leaves_a_row_with_its_result(): void
    {
        $this->runCommand('sync:news', 0);
        $this->runCommand('signals:build', 1);

        $this->assertSame([['sync:news', true], ['signals:build', false]], SyncRun::orderBy('id')->get()->map(fn ($r) => [$r->command, $r->ok])->all());
        $this->assertNotNull(SyncRun::first()->ran_at);
    }

    #[Group('adminSyncRuns')]
    public function test_other_commands_are_not_recorded(): void
    {
        $this->runCommand('migrate', 0);
        $this->runCommand('queue:work', 0);
        $this->runCommand('syncfoo', 0);   // starts with "sync" but not "sync:"

        $this->assertSame(0, SyncRun::count());
    }

    #[Group('adminSyncRuns')]
    public function test_the_recorder_is_wired_to_both_console_events(): void
    {
        // (Laravel does not dispatch the console events for Artisan::call inside a test run, so the wiring is checked here and the
        // behaviour with synthetic events above; in the real app a run of signals:build leaves its row — checked by hand.)
        foreach ([CommandStarting::class, CommandFinished::class] as $event) {
            $this->assertNotEmpty(Event::getListeners($event));
        }
        $this->assertSame(['sync:', 'signals:'], SyncRunRecorder::PREFIXES);
    }

    // ── the page ────────────────────────────────────────────────────────────

    #[Group('adminSyncRuns')]
    public function test_the_page_lists_the_world_and_signals_sources_from_the_cache_without_queueing_anything(): void
    {
        $this->actingAsRole(Role::ADMIN);
        Cache::put(WorldMarketService::CACHE_KEY, ['markets' => [['code' => 'INX'], ['code' => 'DJI']], 'synced_at' => now()->subMinutes(20)->toIso8601String()], 3600);
        Cache::put(StockSignalService::CACHE_KEY, ['universe' => 200, 'built_at' => now()->subMinutes(5)->toIso8601String()], 3600);

        $this->get('/admin/sync-status')->assertOk()
            ->assertSee('Chỉ số thế giới')
            ->assertSee('Tín hiệu hôm nay')
            ->assertSee('sync:world-markets')
            ->assertSee('signals:build');

        Queue::assertNothingPushed();   // reading the cache directly: the services' readers would have queued a refresh job
    }

    #[Group('adminSyncRuns')]
    public function test_a_source_older_than_its_allowance_is_late_and_the_banner_counts_it(): void
    {
        $this->actingAsRole(Role::ADMIN);
        Cache::put(WorldMarketService::CACHE_KEY, ['markets' => [['code' => 'INX']], 'synced_at' => now()->subHours(10)->toIso8601String()], 3600);   // allowed: 3 h

        $html = $this->get('/admin/sync-status')->assertOk()->getContent();

        $this->assertStringContainsString('Trễ', $html);
        $this->assertStringContainsString('nguồn trễ hoặc chưa có dữ liệu', $html);
    }

    #[Group('adminSyncRuns')]
    public function test_the_last_run_and_its_failure_show_on_the_source_card_and_in_the_history(): void
    {
        $this->actingAsRole(Role::ADMIN);
        SyncRun::create(['command' => 'sync:news', 'ok' => true, 'duration_ms' => 1200, 'ran_at' => now()->subHour()]);
        SyncRun::create(['command' => 'sync:news', 'ok' => false, 'duration_ms' => 3400, 'ran_at' => now()->subMinutes(5)]);

        $html = $this->get('/admin/sync-status')->assertOk()->getContent();

        $this->assertStringContainsString('THẤT BẠI', $html, 'the card shows the newest run, which failed');
        $this->assertStringContainsString('Các lần chạy gần nhất', $html);
        $this->assertStringContainsString('Thất bại', $html);
        $this->assertStringContainsString('3,4s', $html);
    }

    #[Group('adminSyncRuns')]
    public function test_the_two_new_commands_are_in_the_manual_trigger_whitelist(): void
    {
        $this->actingAsRole(Role::ADMIN);

        // signals:build needs no Python: without a market snapshot it just says so
        $this->postJson('/admin/sync-status/trigger/signals:build')->assertOk()->assertJsonPath('success', true);
        $this->postJson('/admin/sync-status/trigger/sync:world-markets-evil')->assertStatus(422);
        $this->assertStringContainsString("'sync:world-markets' =>", file_get_contents(base_path('app/Backend/Controllers/SyncStatusController.php')));
    }

    // ── dashboard charts ────────────────────────────────────────────────────

    #[Group('adminDashboardCharts')]
    public function test_signups_are_one_bucket_per_day_for_thirty_days_with_empty_days_included(): void
    {
        User::factory()->create(['created_at' => now()]);
        User::factory()->create(['created_at' => now()]);
        User::factory()->create(['created_at' => now()->subDays(3)]);
        User::factory()->create(['created_at' => now()->subDays(45)]);   // outside the window

        $r = app(DashboardInsightsService::class)->insights();

        $this->assertCount(30, $r['signups']);
        $this->assertSame(3, $r['signups_total']);
        $this->assertSame(3, array_sum(array_column($r['signups'], 'count')));
        $this->assertSame(0, $r['signups'][0]['count'], 'a day without sign-ups is a zero, not a gap');
    }

    #[Group('adminDashboardCharts')]
    public function test_the_most_followed_and_held_symbols_are_ranked(): void
    {
        $users = User::factory()->count(3)->create();
        foreach ($users as $u) {
            WatchlistItem::create(['user_id' => $u->id, 'symbol' => 'FPT']);
        }
        WatchlistItem::create(['user_id' => $users[0]->id, 'symbol' => 'VCB']);
        $pf = fn ($u) => Portfolio::create(['user_id' => $u->id, 'name' => 'p', 'total_invested' => 1, 'current_value' => 1, 'is_active' => true]);
        $hold = fn ($u, $sym) => PortfolioItem::create(['portfolio_id' => $pf($u)->id, 'stock_symbol' => $sym, 'stock_name' => $sym, 'quantity' => 1, 'buy_price' => 1, 'current_price' => 1, 'buy_date' => now()->toDateString()]);
        $hold($users[0], 'HPG');
        $hold($users[1], 'HPG');
        $hold($users[2], 'MWG');

        $r = app(DashboardInsightsService::class)->insights();

        $this->assertSame([['symbol' => 'FPT', 'count' => 3], ['symbol' => 'VCB', 'count' => 1]], $r['top_watched']);
        $this->assertSame([['symbol' => 'HPG', 'count' => 2], ['symbol' => 'MWG', 'count' => 1]], $r['top_held']);
    }

    #[Group('adminDashboardCharts')]
    public function test_activity_is_bucketed_by_vietnam_hour_over_seven_days_and_old_events_are_ignored(): void
    {
        $at = fn (string $utc) => ActivityLog::create(['user_id' => null, 'user_name' => 'x', 'event_type' => 'login', 'description' => 'd', 'created_at' => $utc]);
        $at(now()->subDay()->setTime(2, 30)->toDateTimeString());     // 02:30 UTC = 09:30 in Vietnam
        $at(now()->subDay()->setTime(2, 45)->toDateTimeString());
        $at(now()->subDays(20)->setTime(2, 30)->toDateTimeString());  // too old

        $hours = app(DashboardInsightsService::class)->activityByHour();

        $this->assertCount(24, $hours);
        $this->assertSame(2, $hours[9]);
        $this->assertSame(2, array_sum($hours));
    }

    #[Group('adminDashboardCharts')]
    public function test_the_dashboard_shows_the_charts_and_the_hourly_one_only_with_the_timeline_permission(): void
    {
        $this->actingAsRole(Role::ADMIN);
        $this->get('/admin')->assertOk()->assertSee('Đăng ký mới · 30 ngày')->assertSee('Hoạt động theo giờ')->assertSee('Mã được theo dõi nhiều nhất')
            ->assertDontSee('Cần quyền xem timeline');

        Role::firstWhere('name', Role::ADMIN_SUPPORT)->permissions()->detach(Permission::firstWhere('name', 'view-timeline')->id);
        $this->flushSession();
        $this->actingAsRole(Role::ADMIN_SUPPORT);

        $this->get('/admin')->assertOk()->assertSee('Đăng ký mới · 30 ngày')->assertSee('Cần quyền xem timeline');
    }

    #[Group('adminDashboardCharts')]
    public function test_the_dashboard_lists_the_two_new_sources(): void
    {
        $this->actingAsRole(Role::ADMIN);

        $this->get('/admin')->assertOk()->assertSee('Chỉ số thế giới')->assertSee('Tín hiệu hôm nay');
    }

    #[Group('adminDashboardCharts')]
    public function test_symbol_links_never_carry_anything_but_the_symbol(): void
    {
        $this->actingAsRole(Role::ADMIN);
        Stock::create(['symbol' => 'FPT']);
        WatchlistItem::create(['user_id' => User::factory()->create()->id, 'symbol' => 'FPT']);

        $this->get('/admin')->assertOk()->assertSee('/stock?symbol=FPT', false);
    }
}
