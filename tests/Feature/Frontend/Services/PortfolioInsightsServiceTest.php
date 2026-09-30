<?php

namespace Tests\Feature\Frontend\Services;

use App\Frontend\Services\PortfolioInsightsService;
use App\Frontend\Services\PortfolioLedgerService;
use App\Jobs\RefreshStockPricesJob;
use App\Models\Etf;
use App\Models\Portfolio;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Models\StockSymbol;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * RefreshDatabase: the ledger, the price history and the ETF roster are real rows. `enhance()` is fed an analytics
 * array built by hand so none of this touches PortfolioService's price refresh (which can start Python).
 */
class PortfolioInsightsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Queue::fake();
        Carbon::setTestNow('2026-09-30 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Weekday bars ending today: close `$start` rising `$step` per bar (feed unit = thousands of VND). */
    private function prices(string $symbol, int $days = 260, float $start = 50.0, float $step = 0.05, ?string $from = null): void
    {
        $stock = Stock::firstOrCreate(['symbol' => $symbol]);
        $rows = [];
        $n = 0;
        for ($i = $days; $i >= 0; $i--) {
            $d = now()->subDays($i);
            if ($d->isWeekend() || ($from && $d->toDateString() < $from)) {
                continue;
            }
            $c = $start + $n * $step;
            $rows[] = ['stock_id' => $stock->id, 'date' => $d->toDateString(), 'open' => $c, 'high' => $c, 'low' => $c, 'close' => $c, 'volume' => 1000];
            $n++;
        }
        StockPrice::insert($rows);
    }

    private function portfolio(): Portfolio
    {
        return Portfolio::create(['user_id' => User::factory()->create()->id, 'name' => 'Test', 'total_invested' => 0, 'current_value' => 0, 'is_active' => true]);
    }

    private function buy(Portfolio $p, string $symbol, int $qty, int $price, string $date, string $type = 'buy'): void
    {
        $r = app(PortfolioLedgerService::class)->trade($p->id, $p->user_id, ['type' => $type, 'stock_symbol' => $symbol, 'quantity' => $qty, 'price' => $price, 'fee' => 0, 'traded_at' => $date]);
        $this->assertTrue($r['ok'], $r['message'] ?? '');
    }

    /** @param array<int, array<string, mixed>> $holdings */
    private function analytics(Portfolio $p, array $holdings = []): array
    {
        $p->load('items');

        return ['portfolio' => $p->fresh(), 'holdings' => $holdings, 'suggestions' => [], 'performance' => []];
    }

    private function holding(string $symbol, float $value, float $weight, ?string $name = null): array
    {
        return ['symbol' => $symbol, 'name' => $name ?? $symbol, 'value' => $value, 'weight' => $weight];
    }

    // ── names, sectors, suggestions ─────────────────────────────────────────

    #[Group('portfolioInsights')]
    public function test_etf_rows_get_their_registered_name_instead_of_the_ticker_and_every_row_a_sector(): void
    {
        Etf::create(['symbol' => 'FUESSV30', 'name' => 'Quỹ ETF SSIAM VN30', 'kind' => 'etf']);
        StockSymbol::create(['symbol' => 'VCB', 'name' => 'Vietcombank', 'industry' => 'Ngân hàng']);
        $p = $this->portfolio();

        $h = app(PortfolioInsightsService::class)->enhance($this->analytics($p, [
            $this->holding('FUESSV30', 600, 60), $this->holding('VCB', 400, 40, 'Vietcombank'), $this->holding('XYZ', 0, 0),
        ]))['holdings'];

        $this->assertSame('SSIAM VN30', $h[0]['name']);
        $this->assertTrue($h[0]['is_etf']);
        $this->assertSame('Quỹ ETF / quỹ niêm yết', $h[0]['sector']);
        $this->assertSame('Vietcombank', $h[1]['name']);
        $this->assertSame('Ngân hàng', $h[1]['sector']);
        $this->assertSame('Chưa phân ngành', $h[2]['sector']);
    }

    #[Group('portfolioInsights')]
    public function test_sectors_are_summed_largest_first_with_their_share(): void
    {
        foreach (['VCB' => 'Ngân hàng', 'ACB' => 'Ngân hàng', 'FPT' => 'Công nghệ'] as $s => $i) {
            StockSymbol::create(['symbol' => $s, 'name' => $s, 'industry' => $i]);
        }

        $r = app(PortfolioInsightsService::class)->enhance($this->analytics($this->portfolio(), [
            $this->holding('FPT', 200, 20), $this->holding('VCB', 500, 50), $this->holding('ACB', 300, 30),
        ]));

        $this->assertSame(['Ngân hàng', 'Công nghệ'], array_column($r['sectors'], 'name'));
        $this->assertSame([80.0, 20.0], array_column($r['sectors'], 'percent'));
        $this->assertSame(['VCB', 'ACB'], $r['sectors'][0]['symbols']);
    }

    #[Group('portfolioInsights')]
    public function test_a_large_etf_is_not_a_concentration_problem_but_a_large_single_stock_is(): void
    {
        Etf::create(['symbol' => 'E1VFVN30', 'name' => 'Quỹ ETF DCVFMVN30', 'kind' => 'etf']);
        StockSymbol::create(['symbol' => 'VIC', 'name' => 'Vingroup', 'industry' => 'Bất động sản']);
        StockSymbol::create(['symbol' => 'FPT', 'name' => 'FPT', 'industry' => 'Công nghệ']);

        $r = app(PortfolioInsightsService::class)->enhance($this->analytics($this->portfolio(), [
            $this->holding('E1VFVN30', 400, 40), $this->holding('VIC', 350, 35), $this->holding('FPT', 250, 25),
        ]));

        $titles = array_column($r['suggestions'], 'title');
        $this->assertCount(1, $titles);
        $this->assertStringContainsString('VIC chiếm 35,0%', $titles[0]);
    }

    #[Group('portfolioInsights')]
    public function test_two_etfs_tracking_the_same_index_are_reported_as_overlapping(): void
    {
        Etf::create(['symbol' => 'FUESSV30', 'name' => 'Quỹ ETF SSIAM VN30', 'kind' => 'etf']);
        Etf::create(['symbol' => 'FUEMAV30', 'name' => 'Quỹ ETF MAFM VN30', 'kind' => 'etf']);
        Etf::create(['symbol' => 'FUESSV50', 'name' => 'Quỹ ETF SSIAM VNX50', 'kind' => 'etf']);

        $r = app(PortfolioInsightsService::class)->enhance($this->analytics($this->portfolio(), [
            $this->holding('FUESSV30', 300, 30), $this->holding('FUEMAV30', 300, 30), $this->holding('FUESSV50', 400, 40),
        ]));

        $overlap = array_values(array_filter($r['suggestions'], fn ($s) => $s['type'] === 'overlap'));
        $this->assertCount(1, $overlap);
        $this->assertSame('2 quỹ ETF cùng bám chỉ số VN30', $overlap[0]['title']);
        $this->assertStringContainsString('FUESSV30', $overlap[0]['reason']);
        $this->assertCount(0, array_filter($r['suggestions'], fn ($s) => $s['type'] === 'reduce'));   // ETFs: no single-position cap
    }

    #[Group('portfolioInsights')]
    public function test_one_dominant_industry_is_flagged_but_etfs_and_unclassified_stocks_are_not(): void
    {
        StockSymbol::create(['symbol' => 'VCB', 'name' => 'VCB', 'industry' => 'Ngân hàng']);
        StockSymbol::create(['symbol' => 'ACB', 'name' => 'ACB', 'industry' => 'Ngân hàng']);
        Etf::create(['symbol' => 'E1VFVN30', 'name' => 'Quỹ ETF DCVFMVN30', 'kind' => 'etf']);

        $svc = app(PortfolioInsightsService::class);
        $banks = $svc->enhance($this->analytics($this->portfolio(), [$this->holding('VCB', 300, 28), $this->holding('ACB', 300, 28), $this->holding('E1VFVN30', 400, 44)]));
        $etfOnly = $svc->enhance($this->analytics($this->portfolio(), [$this->holding('E1VFVN30', 1000, 100)]));
        $unknown = $svc->enhance($this->analytics($this->portfolio(), [$this->holding('ZZZ', 1000, 28)]));

        $this->assertSame(['sector'], array_unique(array_column($banks['suggestions'], 'type')));
        $this->assertStringContainsString('Ngành Ngân hàng chiếm 60,0%', $banks['suggestions'][0]['title']);
        $this->assertSame([], $etfOnly['suggestions']);
        $this->assertSame([], $unknown['suggestions']);
    }

    // ── ledger replay, risk, benchmarks ─────────────────────────────────────

    private function withLedger(): Portfolio
    {
        $this->prices('VCB', 260, 50, 0.05);
        $this->prices('VNINDEX', 260, 1200, 1.0);
        $this->prices('E1VFVN30', 260, 30, 0.02);
        $p = $this->portfolio();
        $this->buy($p, 'VCB', 100, 55_000, now()->subDays(200)->toDateString());
        $this->buy($p, 'VCB', 50, 58_000, now()->subDays(120)->toDateString());
        $this->buy($p, 'VCB', 60, 60_000, now()->subDays(20)->toDateString(), 'sell');

        return $p;
    }

    #[Group('portfolioInsights')]
    public function test_the_value_line_is_replayed_from_the_ledger_and_ends_on_todays_real_total(): void
    {
        $p = $this->withLedger();

        $r = app(PortfolioInsightsService::class)->enhance($this->analytics($p, [$this->holding('VCB', 1, 100)]));

        $chart = $r['performance'];
        $this->assertGreaterThan(50, count($chart));
        $this->assertSame((float) $p->fresh()->current_value, end($chart)['value']);
        // 100 shares then 150, then 90 after the sale: the invested (cost basis) line steps down after the sale
        $invested = array_column($chart, 'invested');
        $this->assertGreaterThan(max(array_slice($invested, -10)), max($invested));
        $this->assertArrayNotHasKey('flow', $chart[0]);
    }

    #[Group('portfolioInsights')]
    public function test_risk_figures_and_both_benchmarks_are_produced_with_the_comparison_numbers(): void
    {
        $p = $this->withLedger();

        $r = app(PortfolioInsightsService::class)->enhance($this->analytics($p, [$this->holding('VCB', 1, 100)]));

        $this->assertNotNull($r['risk']['volatility']);
        $this->assertNotNull($r['risk']['max_drawdown']);
        $this->assertGreaterThan(100, $r['risk']['days']);
        $this->assertSame(['VNINDEX', 'E1VFVN30'], array_keys($r['benchmarks']));

        $b = $r['benchmarks']['VNINDEX'];
        $this->assertSame('VN-Index', $b['label']);
        $this->assertTrue($b['covered']);
        $this->assertNotNull($b['return']);
        $this->assertEqualsWithDelta($r['risk']['total_return'] - $b['return'], $b['points_diff'], 0.01);
        $this->assertEqualsWithDelta((float) $p->fresh()->current_value - $b['final_value'], $b['value_diff'], 1.0);
        $this->assertSame(count($r['performance']), count($b['series']));   // same thinning: the two lines share their dates
    }

    #[Group('portfolioInsights')]
    public function test_a_benchmark_whose_history_starts_too_late_is_flagged_and_backfilled_once_a_day(): void
    {
        $this->prices('VCB', 260, 50, 0.05);
        $this->prices('VNINDEX', 260, 1200, 1.0, now()->subDays(60)->toDateString());   // only the last 60 days
        $p = $this->portfolio();
        $this->buy($p, 'VCB', 100, 55_000, now()->subDays(200)->toDateString());
        $svc = app(PortfolioInsightsService::class);

        $first = $svc->enhance($this->analytics($p, [$this->holding('VCB', 1, 100)]));
        Carbon::setTestNow('2026-09-30 11:00:00');   // a new hour: the insights cache key changes, so the benchmark check runs again
        $svc->enhance($this->analytics($p, [$this->holding('VCB', 1, 100)]));

        $this->assertFalse($first['benchmarks']['VNINDEX']['covered']);
        $this->assertNull($first['benchmarks']['VNINDEX']['points_diff']);       // no honest comparison over a shorter period
        Queue::assertPushed(RefreshStockPricesJob::class, 1);                    // once, not on every page view
    }

    #[Group('portfolioInsights')]
    public function test_an_empty_ledger_leaves_risk_and_benchmarks_empty_without_failing(): void
    {
        $r = app(PortfolioInsightsService::class)->enhance($this->analytics($this->portfolio(), []));

        $this->assertNull($r['risk']);
        $this->assertSame([], $r['benchmarks']);
        $this->assertSame([], $r['performance']);
    }
}
