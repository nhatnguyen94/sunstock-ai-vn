<?php

namespace Tests\Feature\Frontend\Services;

use App\Frontend\Interfaces\MarketSnapshotRepositoryInterface;
use App\Frontend\Interfaces\StockRepositoryInterface;
use App\Frontend\Services\MarketOverviewService;
use App\Frontend\Services\StockSignalService;
use App\Jobs\BuildStockSignalsJob;
use App\Models\Stock;
use App\Models\StockPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\BuildsMarketPayload;
use Tests\TestCase;

/**
 * Real SQL for the history queries, the market snapshot seeded, the cache and the queue observed: a page view only reads the cached result,
 * the heavy build runs in the job/command.
 */
class StockSignalServiceTest extends TestCase
{
    use BuildsMarketPayload, RefreshDatabase;

    private const TRADE_DATE = '2026-09-18';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function service(): StockSignalService
    {
        return $this->app->make(StockSignalService::class);
    }

    /** `$sessions` daily bars (feed units) ending the day before the trade date; closes drift between 30 and 40, highs reach 45. */
    private function history(string $symbol, int $sessions = 130): Stock
    {
        $stock = Stock::create(['symbol' => $symbol, 'name' => $symbol]);
        for ($i = 1; $i <= $sessions; $i++) {
            StockPrice::create([
                'stock_id' => $stock->id, 'date' => Carbon::parse(self::TRADE_DATE)->subDays($i)->toDateString(),
                'open' => 35, 'high' => 40 + ($i % 6), 'low' => 30 - ($i % 3), 'close' => 35 + ($i % 4), 'volume' => 1_000_000,
            ]);
        }

        return $stock;
    }

    /** Snapshot quotes: price whole VND, [price, ref, %, volume, value]. */
    private function snapshot(array $quotes): void
    {
        $this->seedMarketSnapshot(['trade_date' => self::TRADE_DATE, 'quotes' => $quotes]);
    }

    #[Group('stockSignalsService')]
    public function test_the_build_finds_a_breakout_a_breakdown_a_volume_spike_and_caches_the_result(): void
    {
        foreach (['UPP', 'DWN', 'VOL', 'CAL'] as $s) {
            $this->history($s);
        }
        $this->snapshot([
            'UPP' => [48_000, 46_000, 4.3, 1_200_000, 57_600_000_000],       // above the highest high (45) of the window
            'DWN' => [27_000, 28_000, -3.5, 1_300_000, 35_100_000_000],      // below the lowest low (28)
            'VOL' => [38_000, 37_500, 1.3, 4_000_000, 152_000_000_000],      // 4x the average volume, inside the range
            'CAL' => [38_000, 37_900, 0.2, 1_000_000, 38_000_000_000],       // nothing special
        ]);

        $r = $this->service()->build();

        $this->assertSame(self::TRADE_DATE, $r['trade_date']);
        $this->assertSame(4, $r['universe']);
        $this->assertSame(['UPP'], array_column($r['breakout'], 's'));
        $this->assertSame(45_000, $r['breakout'][0]['ref'], 'the stored high, converted from feed units');
        $this->assertSame(['DWN'], array_column($r['breakdown'], 's'));
        $this->assertSame(['VOL'], array_column($r['volume'], 's'));
        $this->assertSame(4.0, $r['volume'][0]['ratio']);
        $this->assertSame($r, Cache::get(StockSignalService::CACHE_KEY));
    }

    #[Group('stockSignalsService')]
    public function test_bars_on_or_after_the_trade_date_never_count_as_history(): void
    {
        $stock = $this->history('AAA');
        StockPrice::create(['stock_id' => $stock->id, 'date' => self::TRADE_DATE, 'open' => 1, 'high' => 999, 'low' => 1, 'close' => 40, 'volume' => 1]);   // today's own stored bar
        $this->snapshot(['AAA' => [48_000, 46_000, 4.3, 1_200_000, 57_600_000_000]]);

        $r = $this->service()->build();

        $this->assertSame(['AAA'], array_column($r['breakout'], 's'), "today's stored high of 999 must not hide the breakout");
    }

    #[Group('stockSignalsService')]
    public function test_a_stock_with_a_short_history_is_not_judged(): void
    {
        $this->history('NEW', 30);
        $this->snapshot(['NEW' => [48_000, 46_000, 4.3, 1_200_000, 57_600_000_000]]);

        $r = $this->service()->build();

        $this->assertSame(0, $r['universe']);
        $this->assertSame([], $r['breakout']);
    }

    #[Group('stockSignalsService')]
    public function test_without_a_snapshot_there_is_nothing_to_build(): void
    {
        $this->assertNull($this->service()->build());
        $this->assertNull(Cache::get(StockSignalService::CACHE_KEY));
    }

    // ── what a page view does ───────────────────────────────────────────────

    #[Group('stockSignalsService')]
    public function test_with_nothing_cached_a_page_gets_null_and_exactly_one_build_is_queued(): void
    {
        $this->assertNull($this->service()->cached());
        $this->assertNull($this->service()->cached());

        Queue::assertPushed(BuildStockSignalsJob::class, 1);
    }

    #[Group('stockSignalsService')]
    public function test_a_fresh_result_is_served_without_queueing_anything(): void
    {
        Cache::put(StockSignalService::CACHE_KEY, ['universe' => 3, 'built_at' => now()->toIso8601String()], now()->addDay());

        $this->assertSame(3, $this->service()->cached()['universe']);
        Queue::assertNotPushed(BuildStockSignalsJob::class);
    }

    #[Group('stockSignalsService')]
    public function test_during_the_session_an_old_result_is_served_and_rebuilt_in_the_background(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-16 10:00:00', 'Asia/Ho_Chi_Minh'));   // a Wednesday morning: the market is open
        Cache::put(StockSignalService::CACHE_KEY, ['universe' => 3, 'built_at' => now()->subMinutes(StockSignalService::STALE_OPEN_MINUTES + 1)->toIso8601String()], now()->addDay());

        $this->assertSame(3, $this->service()->cached()['universe']);
        Queue::assertPushed(BuildStockSignalsJob::class, 1);
        Carbon::setTestNow();
    }

    #[Group('stockSignalsService')]
    public function test_outside_the_session_the_same_age_is_still_fresh_enough(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-19 11:00:00', 'Asia/Ho_Chi_Minh'));   // a Saturday
        Cache::put(StockSignalService::CACHE_KEY, ['universe' => 3, 'built_at' => now()->subHours(2)->toIso8601String()], now()->addDay());

        $this->service()->cached();

        Queue::assertNotPushed(BuildStockSignalsJob::class);
        Carbon::setTestNow();
    }

    #[Group('stockSignalsService')]
    public function test_the_page_never_runs_the_heavy_queries(): void
    {
        $stocks = Mockery::mock(StockRepositoryInterface::class);
        $stocks->shouldNotReceive('priceExtremes');
        $stocks->shouldNotReceive('recentBars');
        $service = new StockSignalService($this->app->make(MarketSnapshotRepositoryInterface::class), $stocks, $this->app->make(MarketOverviewService::class));

        $this->assertNull($service->cached());
    }

    #[Group('stockSignalsService')]
    public function test_the_job_and_the_command_run_the_build(): void
    {
        $this->history('UPP');
        $this->snapshot(['UPP' => [48_000, 46_000, 4.3, 1_200_000, 57_600_000_000]]);

        (new BuildStockSignalsJob)->handle($this->service());
        $this->assertSame(['UPP'], array_column(Cache::get(StockSignalService::CACHE_KEY)['breakout'], 's'));

        Cache::flush();
        $this->artisan('signals:build')->expectsOutputToContain('1 stocks evaluated, 1 breakouts')->assertExitCode(0);
    }

    #[Group('stockSignalsService')]
    public function test_the_command_says_what_to_do_when_there_is_no_snapshot(): void
    {
        $this->artisan('signals:build')->expectsOutputToContain('run sync:market-overview first')->assertExitCode(1);
    }

    #[Group('stockSignalsService')]
    public function test_the_price_queries_group_by_symbol_and_respect_the_date_bounds(): void
    {
        $stock = $this->history('AAA', 10);
        $repo = $this->app->make(StockRepositoryInterface::class);

        $extremes = $repo->priceExtremes('2026-09-01', self::TRADE_DATE);
        $bars = $repo->recentBars('2026-09-01', self::TRADE_DATE);

        $this->assertSame(10, $extremes['AAA']['n']);
        $this->assertSame('2026-09-17', $extremes['AAA']['last_date']);
        $this->assertCount(10, $bars['AAA']);
        $this->assertSame('2026-09-08', $bars['AAA'][0][0], 'oldest first');
        $this->assertSame([], $repo->priceExtremes('2026-09-17', '2026-09-17'), 'an empty window is empty');
        $this->assertSame($stock->id, Stock::where('symbol', 'AAA')->value('id'));
    }
}
