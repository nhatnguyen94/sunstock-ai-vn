<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Frontend\Interfaces\StockRepositoryInterface;
use App\Frontend\Repositories\StockRepository;
use App\Frontend\Services\MarketHeatmapService;
use App\Models\ExchangeRate;
use App\Models\HotIndustry;
use App\Models\MarketSnapshot;
use App\Models\StockSymbol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Concerns\BuildsMarketPayload;
use Tests\TestCase;

/**
 * The home page heat map end to end: the service reading the stored snapshot, the JSON poll, the home markup and the
 * data handed to the script. Snapshots are seeded, so Python is never reached.
 */
class MarketHeatmapPageTest extends TestCase
{
    use BuildsMarketPayload, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        Cache::flush();
        HotIndustry::create(['symbol' => 'VCB', 'organ_name' => 'Vietcombank', 'icb_name3' => 'Ngân hàng']);
        ExchangeRate::create(['currency_code' => 'USD', 'currency_name' => 'US DOLLAR', 'buy_cash' => '25000', 'buy_transfer' => '25050', 'sell' => '25400', 'date' => now()->format('Y-m-d')]);
        StockSymbol::create(['symbol' => 'FPT', 'name' => 'FPT Corp', 'exchange' => 'HSX', 'industry' => 'Công nghệ và thông tin']);
        StockSymbol::create(['symbol' => 'VIC', 'name' => 'Vingroup', 'exchange' => 'HSX', 'industry' => 'Bất động sản']);
        StockSymbol::create(['symbol' => 'NVB', 'name' => 'NCB', 'exchange' => 'HSX', 'industry' => 'Ngân hàng']);
        // HPG has no industry on purpose; ETF did not trade
    }

    /** Quotes with the exchange as 11th element, the way the script writes them now. */
    private function seedWithExchanges(): MarketSnapshot
    {
        return $this->seedMarketSnapshot(['quotes' => [
            'VIC' => [190500, 190500, 0.0, 16_800_000, 3_197_000_000_000, 203800, 177200, 190500, 190500, 190500, 'HOSE'],
            'FPT' => [71700, 74300, -3.5, 15_500_700, 1_129_620_000_000, 79500, 69100, 74500, 74800, 71700, 'HOSE'],
            'NVB' => [12800, 11700, 9.4, 2_200_000, 27_000_000_000, 12900, 10500, 11800, 12900, 11700, 'HNX'],
            'HPG' => [28500, 26650, 6.94, 30_000_000, 850_000_000_000, 28500, 24800, 26700, 28500, 26600, 'HOSE'],
            'ETF' => [10000, 10000, 0.0, 0, 0, 10700, 9300, null, null, null, 'HOSE'],
        ]]);
    }

    private function service(): MarketHeatmapService
    {
        return $this->app->make(MarketHeatmapService::class);
    }

    // ── service ─────────────────────────────────────────────────────────────

    #[Group('marketHeatmap')]
    public function test_the_service_builds_tiles_from_the_newest_snapshot_with_industries(): void
    {
        $this->seedWithExchanges();

        $map = $this->service()->heatmap();

        $this->assertSame(['VIC', 'FPT', 'HPG', 'NVB'], array_column($map['items'], 's'), 'busiest first; the stock that did not trade has no tile');
        $byS = collect($map['items'])->keyBy('s');
        $this->assertSame('Công nghệ và thông tin', $byS['FPT']['i']);
        $this->assertSame('Khác', $byS['HPG']['i'], 'no industry on file');
        $this->assertSame('HNX', $byS['NVB']['x']);
        $this->assertSame('Vingroup', $byS['VIC']['n']);
        $this->assertSame(4, $map['shown']);
        $this->assertSame('2026-09-18', $map['trade_date']);
        $this->assertNotEmpty($map['as_of']);
    }

    #[Group('marketHeatmap')]
    public function test_without_a_snapshot_or_without_quotes_there_is_no_heatmap(): void
    {
        $this->assertNull($this->service()->heatmap());

        $this->seedMarketSnapshot(['quotes' => []]);
        $this->assertNull($this->service()->heatmap());
    }

    #[Group('marketHeatmap')]
    public function test_the_shaped_result_is_cached_per_snapshot_and_rebuilt_for_a_new_one(): void
    {
        $this->seedWithExchanges();
        $first = $this->service()->heatmap();

        StockSymbol::query()->delete();                 // would change the result if it were rebuilt
        $this->assertSame($first, $this->service()->heatmap(), 'same snapshot: served from the cache');

        MarketSnapshot::query()->update(['synced_at' => now()->addMinutes(5)]);
        $rebuilt = $this->service()->heatmap();
        $this->assertSame('Khác', collect($rebuilt['items'])->firstWhere('s', 'FPT')['i'], 'a newer sync builds it again');
    }

    #[Group('marketHeatmap')]
    public function test_symbol_info_returns_name_exchange_and_industry_and_skips_unknown_symbols(): void
    {
        $repo = $this->app->make(StockRepository::class);

        $info = $repo->symbolInfo(['FPT', 'NOPE']);

        $this->assertSame(['FPT' => ['name' => 'FPT Corp', 'exchange' => 'HSX', 'industry' => 'Công nghệ và thông tin']], $info);
        $this->assertSame([], $repo->symbolInfo([]));
    }

    #[Group('marketHeatmap')]
    public function test_a_hostile_symbol_list_is_just_data_for_the_lookup(): void
    {
        $repo = $this->app->make(StockRepository::class);

        $this->assertSame([], $repo->symbolInfo(["FPT'; DROP TABLE stock_symbols; --", '%', '_']));
        $this->assertSame(3, StockSymbol::count());
    }

    // ── JSON poll ───────────────────────────────────────────────────────────

    #[Group('marketHeatmap')]
    public function test_the_poll_carries_the_heatmap(): void
    {
        $this->seedWithExchanges();

        $json = $this->getJson('/market/data')->assertOk()->json();

        $this->assertTrue($json['success']);
        $this->assertSame(['VIC', 'FPT', 'HPG', 'NVB'], array_column($json['heatmap']['items'], 's'));
        $this->assertSame(['s', 'n', 'x', 'i', 'p', 'c', 'v', 'q'], array_keys($json['heatmap']['items'][0]));
    }

    #[Group('marketHeatmap')]
    public function test_a_failing_heatmap_does_not_take_the_poll_down(): void
    {
        $this->seedWithExchanges();
        $stub = Mockery::mock(MarketHeatmapService::class);
        $stub->shouldReceive('heatmap')->andThrow(new RuntimeException('boom'));
        $this->app->instance(MarketHeatmapService::class, $stub);

        $json = $this->getJson('/market/data')->assertOk()->json();

        $this->assertTrue($json['success']);
        $this->assertNull($json['heatmap']);
        $this->assertNotEmpty($json['indices'], 'the rest of the poll is intact');
    }

    // ── home page ───────────────────────────────────────────────────────────

    #[Group('marketHeatmap')]
    public function test_home_renders_the_heatmap_card_under_the_index_cards_and_hands_the_data_to_the_script(): void
    {
        $this->seedWithExchanges();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="mkHeatStage"', $html);
        $this->assertStringContainsString('Bản đồ nhiệt thị trường', $html);
        $this->assertStringContainsString('id="mkHeatTip"', $html);
        $this->assertStringContainsString('id="mkHeatLegend"', $html);
        $this->assertStringContainsString('data-ex="UPCOM"', $html);
        $this->assertStringContainsString('"heatmap":{"items":', $html);

        $indices = strpos($html, 'class="mk-indices"');
        $heat = strpos($html, 'id="mkHeat"');
        $chart = strpos($html, 'id="mkChart"');
        $this->assertTrue($indices < $heat && $heat < $chart, 'index cards, then the heat map, then the VN-Index chart');
    }

    #[Group('marketHeatmap')]
    public function test_the_card_can_be_folded_with_an_accessible_toggle_and_a_remembered_choice(): void
    {
        $this->seedWithExchanges();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<button type="button" class="mk-heat-toggle" id="mkHeatToggle" aria-expanded="true" aria-controls="mkHeatBody"#', $html);
        $this->assertStringContainsString('id="mkHeatBody"', $html);
        $this->assertStringContainsString('id="mkHeatMini"', $html);    // the one-line summary shown while folded
        $this->assertStringContainsString('sr-only', $html);            // the icon-only button has a text label

        // the stage, legend and footer are all inside the part that folds away; the head with the toggle is outside it
        $head = strpos($html, 'id="mkHeatToggle"');
        $body = strpos($html, 'id="mkHeatBody"');
        $stage = strpos($html, 'id="mkHeatStage"');
        $legend = strpos($html, 'id="mkHeatLegend"');
        $this->assertTrue($head < $body && $body < $stage && $stage < $legend);

        // the remembered choice is applied right after the card, before the first paint; phones start folded
        $this->assertStringContainsString("localStorage.getItem('sunstock-heatmap')", $html);
        $this->assertStringContainsString("s === 'closed' || (s === null && window.innerWidth < 768)", $html);
        $this->assertGreaterThan(strpos($html, 'id="mkHeat"'), strpos($html, "localStorage.getItem('sunstock-heatmap')"));
    }

    #[Group('marketHeatmap')]
    public function test_folding_is_wired_in_the_script_and_the_stylesheet(): void
    {
        $js = file_get_contents(resource_path('frontend/js/market/heatmap.js'));
        $home = file_get_contents(resource_path('frontend/js/market/home.js'));
        $css = file_get_contents(resource_path('frontend/css/market/market.css'));

        $this->assertStringContainsString("classList.toggle('is-collapsed')", $js);
        $this->assertStringContainsString("setAttribute('aria-expanded'", $js);
        $this->assertMatchesRegularExpression("/try \{ localStorage\.setItem\(STATE_KEY.*catch/", $js, 'a blocked storage must not break the toggle');
        $this->assertStringContainsString("toggle: $('mkHeatToggle')", $home);
        $this->assertStringContainsString("$('mkHeatMini')", $home);

        $this->assertStringContainsString('.mk-heat.is-collapsed .mk-heat-body { grid-template-rows: 0fr; }', $css);
        $this->assertStringContainsString('.mk-heat.is-collapsed .mk-heat-inner { visibility: hidden;', $css, 'folded content leaves the tab order');
        $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion: reduce\) \{[^}]*\.mk-heat-body[^}]*transition: none/s', $css);
    }

    #[Group('marketHeatmap')]
    public function test_home_without_heatmap_data_shows_no_card_and_still_works(): void
    {
        $this->seedMarketSnapshot(['quotes' => []]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="mkHeatStage"', $html);
        $this->assertStringContainsString('id="mkChart"', $html);
        $this->assertStringContainsString('"heatmap":null', $html);
    }

    #[Group('marketHeatmap')]
    public function test_home_survives_a_failing_heatmap_service(): void
    {
        $this->seedWithExchanges();
        $stub = Mockery::mock(MarketHeatmapService::class);
        $stub->shouldReceive('heatmap')->andThrow(new RuntimeException('boom'));
        $this->app->instance(MarketHeatmapService::class, $stub);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="mkHeatStage"', $html);
        $this->assertStringContainsString('id="mkChart"', $html);
    }

    #[Group('marketHeatmap')]
    public function test_the_tile_data_is_escaped_inside_the_page_script(): void
    {
        StockSymbol::create(['symbol' => 'XSS', 'name' => '</script><img src=x onerror=alert(1)>', 'exchange' => 'HSX', 'industry' => '"><script>alert(2)</script>']);
        $this->seedMarketSnapshot(['quotes' => [
            'XSS' => [10000, 10000, 1.0, 10, 5_000_000_000, null, null, null, null, null, 'HOSE'],
        ]]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('</script><img', $html);
        $this->assertStringNotContainsString('<script>alert(2)', $html);
    }

    #[Group('marketHeatmap')]
    public function test_the_repository_is_resolved_through_its_interface(): void
    {
        $this->assertInstanceOf(StockRepository::class, $this->app->make(StockRepositoryInterface::class));
    }
}
