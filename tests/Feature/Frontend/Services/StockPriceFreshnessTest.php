<?php

namespace Tests\Feature\Frontend\Services;

use App\Frontend\Repositories\MarketSnapshotRepository;
use App\Frontend\Services\MarketOverviewService;
use App\Frontend\Services\StockPriceFreshness;
use App\Frontend\Services\StockService;
use App\Jobs\RefreshStockPricesJob;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Models\StockSymbol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\BuildsMarketPayload;
use Tests\TestCase;

/**
 * The stock page must never wait on a data provider for a symbol that already has history. Real DB; StockService
 * (the Python boundary) is a mock; the market snapshot is seeded.
 */
class StockPriceFreshnessTest extends TestCase
{
    use RefreshDatabase, BuildsMarketPayload;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Queue::fake();
        // A Sunday: the last completed session is Friday 2026-09-18
        Carbon::setTestNow('2026-09-20 03:00:00');
        foreach (['FPT', 'HPG', 'VNM'] as $s) {
            StockSymbol::create(['symbol' => $s, 'name' => $s . ' Corp', 'exchange' => 'HSX']);
        }
    }

    private function freshness(?StockService $stocks = null): StockPriceFreshness
    {
        $stocks ??= Mockery::mock(StockService::class);

        return new StockPriceFreshness($stocks, new MarketOverviewService(new MarketSnapshotRepository));
    }

    private function bar(string $symbol, string $date, float $close = 70.0): void
    {
        $stock = Stock::firstOrCreate(['symbol' => $symbol]);
        StockPrice::create(['stock_id' => $stock->id, 'date' => $date, 'open' => $close, 'high' => $close, 'low' => $close, 'close' => $close, 'volume' => 100]);
    }

    // ── ensure ──────────────────────────────────────────────────────────────

    #[Group('stockFreshness')]
    public function test_a_symbol_with_current_history_costs_nothing(): void
    {
        $this->bar('FPT', '2026-09-18');
        $stocks = Mockery::mock(StockService::class);
        $stocks->shouldReceive('refreshPrices')->never();

        $r = $this->freshness($stocks)->ensure('FPT');

        $this->assertSame(['status' => 'fresh', 'latest' => '2026-09-18'], $r);
        Queue::assertNothingPushed();
    }

    #[Group('stockFreshness')]
    public function test_a_stale_symbol_is_served_at_once_and_one_incremental_job_is_queued(): void
    {
        $this->bar('FPT', '2026-09-11');
        $stocks = Mockery::mock(StockService::class);
        $stocks->shouldReceive('refreshPrices')->never();          // no Python inside the request
        $f = $this->freshness($stocks);

        $first = $f->ensure('FPT');
        $f->ensure('FPT');                                          // a second visitor

        $this->assertSame('queued', $first['status']);
        Queue::assertPushed(RefreshStockPricesJob::class, 1);       // deduplicated
        Queue::assertPushed(RefreshStockPricesJob::class, function ($job) {
            $props = (fn () => [$this->symbols, $this->from])->call($job);

            return $props === [['FPT'], '2026-09-06'];              // latest 09-11 minus 5 days of overlap
        });
    }

    #[Group('stockFreshness')]
    public function test_a_symbol_without_history_gets_one_short_synchronous_fetch(): void
    {
        $stocks = Mockery::mock(StockService::class);
        $stocks->shouldReceive('refreshPrices')->once()->with(['FPT'], null, StockPriceFreshness::SYNC_TIMEOUT)
            ->andReturnUsing(function () {
                $this->bar('FPT', '2026-09-18');

                return ['stored' => 1, 'errors' => []];
            });

        $r = $this->freshness($stocks)->ensure('FPT');

        $this->assertSame(['status' => 'synced', 'latest' => '2026-09-18'], $r);
        Queue::assertNothingPushed();
    }

    #[Group('stockFreshness')]
    public function test_a_failed_first_fetch_is_remembered_so_the_next_visitor_does_not_wait_again(): void
    {
        $stocks = Mockery::mock(StockService::class);
        $stocks->shouldReceive('refreshPrices')->once()->andReturn(['stored' => 0, 'errors' => ['FPT' => 'KBS-direct: timed out']]);
        $f = $this->freshness($stocks);

        $first = $f->ensure('FPT');
        $second = $f->ensure('FPT');                                 // refreshPrices ->once(): a 2nd call would fail the test

        $this->assertSame('failed', $first['status']);
        $this->assertStringContainsString('FPT', $first['error']);
        $this->assertSame('failed', $second['status']);
    }

    #[Group('stockFreshness')]
    public function test_unknown_codes_never_reach_python_or_pollute_the_stocks_table(): void
    {
        $stocks = Mockery::mock(StockService::class);
        $stocks->shouldReceive('refreshPrices')->never();

        $r = $this->freshness($stocks)->ensure('ZZZQ');

        $this->assertSame('unknown', $r['status']);
        $this->assertSame(0, Stock::where('symbol', 'ZZZQ')->count());
    }

    #[Group('stockFreshness')]
    public function test_indices_are_known_although_they_are_not_in_the_symbol_list(): void
    {
        $stocks = Mockery::mock(StockService::class);
        $stocks->shouldReceive('refreshPrices')->once()->andReturnUsing(function () {
            $this->bar('VNINDEX', '2026-09-18', 1815.66);

            return ['stored' => 1, 'errors' => []];
        });

        $this->assertSame('synced', $this->freshness($stocks)->ensure('VNINDEX')['status']);
    }

    #[Group('stockFreshness')]
    public function test_several_symbols_without_history_share_one_python_run(): void
    {
        $stocks = Mockery::mock(StockService::class);
        $stocks->shouldReceive('refreshPrices')->once()->with(['FPT', 'HPG', 'VNM'], null, StockPriceFreshness::SYNC_TIMEOUT)
            ->andReturnUsing(function () {
                foreach (['FPT', 'HPG'] as $s) {       // VNM has no data at the source
                    $this->bar($s, '2026-09-18');
                }

                return ['stored' => 2, 'errors' => []];
            });

        $r = $this->freshness($stocks)->ensureMany(['VNM', 'FPT', 'HPG', 'FPT']);

        $this->assertSame('synced', $r['FPT']['status']);
        $this->assertSame('synced', $r['HPG']['status']);
        $this->assertSame('failed', $r['VNM']['status']);
    }

    // ── live bar ────────────────────────────────────────────────────────────

    #[Group('stockFreshness')]
    public function test_the_newest_session_is_painted_from_the_snapshot_in_feed_units(): void
    {
        $this->bar('FPT', '2026-09-17');
        $this->seedMarketSnapshot();                                 // session 2026-09-18
        $f = $this->freshness();

        $bar = $f->liveBar('FPT', '2026-09-17');

        $this->assertEqualsWithDelta(74.5, $bar['open'], 0.0001);    // whole VND -> thousands, like stock_prices
        $this->assertEqualsWithDelta(74.8, $bar['high'], 0.0001);
        $this->assertEqualsWithDelta(71.7, $bar['low'], 0.0001);
        $this->assertEqualsWithDelta(71.7, $bar['close'], 0.0001);
        $this->assertSame(15_500_700, $bar['volume']);
        $this->assertSame(Carbon::parse('2026-09-18')->timestamp * 1000, $bar['time']);
    }

    #[Group('stockFreshness')]
    public function test_no_live_bar_when_history_already_has_the_session_or_the_symbol_did_not_trade(): void
    {
        $this->seedMarketSnapshot();
        $f = $this->freshness();

        $this->assertNull($f->liveBar('FPT', '2026-09-18'));         // stored already
        $this->assertNull($f->liveBar('FPT', '2026-09-19'));
        $this->assertNull($f->liveBar('ETF', null));                 // zero volume
        $this->assertNull($f->liveBar('NOPE', null));                // not in the snapshot
        $this->assertNotNull($f->liveBar('FPT', null));              // no history at all
    }

    #[Group('stockFreshness')]
    public function test_with_live_bar_appends_the_candle_and_says_whether_the_session_is_running(): void
    {
        $this->seedMarketSnapshot();
        $rows = [['time' => Carbon::parse('2026-09-17')->timestamp * 1000, 'open' => 74.0, 'high' => 74.5, 'low' => 73.0, 'close' => 74.3, 'volume' => 1]];

        $r = $this->freshness()->withLiveBar($rows, 'FPT');

        $this->assertCount(2, $r['rows']);
        $this->assertSame('2026-09-18', $r['live']['date']);
        $this->assertFalse($r['live']['running']);                   // Sunday: nothing is running

        // Friday 10:00 in Vietnam: the session is open and the snapshot is that day's
        Carbon::setTestNow(Carbon::parse('2026-09-18 10:00:00', 'Asia/Ho_Chi_Minh'));
        $running = $this->freshness()->withLiveBar($rows, 'FPT');
        $this->assertTrue($running['live']['running']);

        $untouched = $this->freshness()->withLiveBar($rows, 'NOPE');
        $this->assertSame($rows, $untouched['rows']);
        $this->assertNull($untouched['live']);
    }

    #[Group('stockFreshness')]
    public function test_symbol_shape_validation(): void
    {
        $this->assertTrue(StockPriceFreshness::isValidSymbol('FPT'));
        $this->assertTrue(StockPriceFreshness::isValidSymbol('E1VFVN30'));
        $this->assertFalse(StockPriceFreshness::isValidSymbol('F'));
        $this->assertFalse(StockPriceFreshness::isValidSymbol('fpt'));
        $this->assertFalse(StockPriceFreshness::isValidSymbol('FPT;ls'));
        $this->assertFalse(StockPriceFreshness::isValidSymbol("FPT\n"));
    }
}
