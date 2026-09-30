<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Frontend\Services\EtfService;
use App\Frontend\Services\WatchlistService;
use App\Models\Etf;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Models\StockSymbol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\BuildsMarketPayload;
use Tests\TestCase;

class EtfControllerTest extends TestCase
{
    use RefreshDatabase, BuildsMarketPayload;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // The layout's ticker and the live prices need a snapshot; without one the first visit would run Python
        $this->seedMarketSnapshot(['quotes' => ['FUESSV30' => [24400, 24200, 0.83, 16_000, 390_000_000, 25900, 22500, 24200, 24500, 24100]]]);

        foreach (['FUESSV30' => 'Quỹ ETF SSIAM VN30', 'FUEMAV30' => 'Quỹ ETF MAFM VN30'] as $symbol => $name) {
            Etf::create(['symbol' => $symbol, 'name' => $name, 'exchange' => 'HOSE', 'kind' => 'etf', 'synced_at' => now()]);
            StockSymbol::create(['symbol' => $symbol, 'name' => $name, 'exchange' => 'HSX']);   // the watchlist only accepts listed symbols
            $stock = Stock::create(['symbol' => $symbol]);
            foreach ([now()->subDays(2), now()->subDay(), now()] as $i => $d) {
                $c = 23.0 + $i;
                StockPrice::create(['stock_id' => $stock->id, 'date' => $d->toDateString(), 'open' => $c, 'high' => $c, 'low' => $c, 'close' => $c, 'volume' => 1000]);
            }
        }
    }

    #[Group('etf')]
    public function test_the_catalog_lists_the_funds_with_index_manager_and_the_compare_bar(): void
    {
        $r = $this->get('/etf');

        $r->assertOk()
            ->assertSee('Quỹ ETF')
            ->assertSee('FUESSV30')
            ->assertSee('FUEMAV30')
            ->assertSee('SSIAM')
            ->assertSee('VN30')
            ->assertSee('id="fdCompareBar"', false)
            ->assertSee('data-watch="FUESSV30"', false)
            ->assertSee('ETF là gì?')
            ->assertSee('chưa có NAV', false);
    }

    #[Group('etf')]
    public function test_filters_and_sorting_are_applied_from_the_query_string(): void
    {
        $this->get('/etf?q=mafm')->assertOk()->assertSee('FUEMAV30')->assertDontSee('data-watch="FUESSV30"', false);
        $this->get('/etf?kind=closed')->assertOk()->assertSee('Không có quỹ nào khớp bộ lọc.');
        $this->get('/etf?sort=<script>&dir=up&kind=%00&index=%27')->assertOk();
    }

    #[Group('etf')]
    public function test_the_detail_page_shows_the_price_the_index_note_the_peers_and_the_chart_data(): void
    {
        $r = $this->get('/etf/fuessv30');

        $r->assertOk()
            ->assertSee('FUESSV30')
            ->assertSee('24.400')                        // live price, whole VND
            ->assertSee('Bám chỉ số VN30')
            ->assertSee('Các quỹ cùng bám VN30')
            ->assertSee('FUEMAV30')
            ->assertSee('window.__ETF_DETAIL__', false)
            ->assertSee(route('portfolio.quick-add', ['symbol' => 'FUESSV30']), false);
    }

    #[Group('etf')]
    public function test_unknown_or_malformed_symbols_are_404_and_never_reach_a_query_of_their_own(): void
    {
        $this->get('/etf/NOPE123')->assertNotFound();
        $this->get('/etf/AB')->assertNotFound();               // route constraint: 3-12 alphanumerics
        $this->get('/etf/FUESSV30%0A')->assertNotFound();
    }

    #[Group('etf')]
    public function test_the_star_state_is_the_signed_in_users_own_watchlist_only(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $watch = app(WatchlistService::class);
        $watch->add($me->id, 'FUESSV30');
        $watch->add($other->id, 'FUEMAV30');

        $mine = $this->actingAs($me)->get('/etf')->getContent();
        $this->assertStringContainsString('"watched":["FUESSV30"]', $mine);
        $this->assertStringNotContainsString('FUEMAV30"]', $mine);

        auth()->logout();
        $guest = $this->get('/etf')->getContent();
        $this->assertStringContainsString('"auth":false,"watched":[]', $guest);
    }

    #[Group('etf')]
    public function test_the_navbar_links_to_the_etf_page(): void
    {
        $this->get('/etf')->assertSee(route('etf.index'), false)->assertSee('Quỹ ETF');
    }

    // ── command + admin ─────────────────────────────────────────────────────

    #[Group('etf')]
    public function test_sync_command_reports_the_count_and_what_it_removed(): void
    {
        $mock = Mockery::mock(EtfService::class);
        $mock->shouldReceive('syncAll')->once()->andReturn(['count' => 24, 'pruned' => 1]);
        $this->app->instance(EtfService::class, $mock);

        $this->artisan('sync:etfs')->expectsOutputToContain('Synced 24 ETFs/funds (1 no longer listed removed)')->assertExitCode(0);
    }

    #[Group('etf')]
    public function test_sync_command_fails_with_a_non_zero_exit_code_on_error(): void
    {
        $bad = Mockery::mock(EtfService::class);
        $bad->shouldReceive('syncAll')->once()->andReturn(['error' => 'KBS down']);
        $this->app->instance(EtfService::class, $bad);
        $this->artisan('sync:etfs')->expectsOutputToContain('KBS down')->assertExitCode(1);
    }

    #[Group('etf')]
    public function test_the_roster_sync_is_scheduled_weekly_and_listed_in_admin_sync_status(): void
    {
        $mock = Mockery::mock(EtfService::class);
        $mock->shouldReceive('syncAll')->once()->andReturn(['count' => 24, 'pruned' => 0]);
        $this->app->instance(EtfService::class, $mock);

        $this->artisan('schedule:list')->expectsOutputToContain('sync:etfs')->assertExitCode(0);

        $role = Role::create(['name' => Role::WEBADMIN, 'display_name' => 'Web Admin']);
        $role->permissions()->sync([Permission::create(['name' => 'manage-features', 'display_name' => 'x'])->id]);
        $admin = User::factory()->create();
        $admin->roles()->attach($role->id);
        $this->actingAs($admin);

        $this->get('/admin/sync-status')->assertOk()->assertSee('Quỹ ETF (KBS)')->assertSee('sync:etfs');

        $this->postJson('/admin/sync-status/trigger/sync:etfs')->assertOk()->assertJsonPath('success', true);
    }
}
