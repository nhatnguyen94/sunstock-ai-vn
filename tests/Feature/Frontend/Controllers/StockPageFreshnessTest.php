<?php

namespace Tests\Feature\Frontend\Controllers;

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
 * GET /stock?symbol= and /stock/compare-data through the real routes. StockService (Python) is a mock that FAILS the
 * test if a request tries to run it when it should not.
 */
class StockPageFreshnessTest extends TestCase
{
    use RefreshDatabase, BuildsMarketPayload;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        Cache::flush();
        Carbon::setTestNow('2026-09-20 03:00:00');      // Sunday: last completed session = Friday 2026-09-18
        StockSymbol::create(['symbol' => 'FPT', 'name' => 'FPT Corp', 'exchange' => 'HSX']);
        StockSymbol::create(['symbol' => 'HPG', 'name' => 'Hòa Phát', 'exchange' => 'HSX']);
    }

    private function noPython(): void
    {
        $stocks = Mockery::mock(StockService::class);
        $stocks->shouldReceive('refreshPrices')->never();
        $stocks->shouldReceive('fetchStockDataFromPython')->never();
        $this->app->instance(StockService::class, $stocks);
    }

    private function bar(string $symbol, string $date, float $close = 70.0): void
    {
        $stock = Stock::firstOrCreate(['symbol' => $symbol]);
        StockPrice::create(['stock_id' => $stock->id, 'date' => $date, 'open' => $close, 'high' => $close, 'low' => $close, 'close' => $close, 'volume' => 100]);
    }

    #[Group('stockFreshness')]
    public function test_a_stale_symbol_renders_from_stored_data_without_python_and_queues_the_missing_sessions(): void
    {
        $this->noPython();
        $this->bar('FPT', '2026-09-10', 72.0);
        $this->bar('FPT', '2026-09-11', 72.7);

        $r = $this->get('/stock?symbol=fpt');

        $r->assertOk();
        $r->assertSee('FPT Corp');
        $r->assertSee('const stockSymbol = \'FPT\'', false);
        Queue::assertPushed(RefreshStockPricesJob::class, 1);
    }

    #[Group('stockFreshness')]
    public function test_the_newest_session_comes_from_the_market_snapshot_and_is_flagged_on_the_page(): void
    {
        $this->noPython();
        $this->bar('FPT', '2026-09-17', 74.3);
        $this->seedMarketSnapshot();                     // FPT closed 71,700 on 2026-09-18

        $r = $this->get('/stock?symbol=FPT');

        $r->assertOk();
        $r->assertSee('từ bảng giá');
        $r->assertSee('18/09/2026');
        $this->assertStringContainsString('"close":71.7', $r->getContent());
    }

    #[Group('stockFreshness')]
    public function test_a_symbol_that_is_up_to_date_makes_no_python_call_and_no_job(): void
    {
        $this->noPython();
        $this->bar('FPT', '2026-09-18');

        $this->get('/stock?symbol=FPT')->assertOk();

        Queue::assertNothingPushed();
    }

    #[Group('stockFreshness')]
    public function test_a_symbol_without_history_is_fetched_once_and_then_shown(): void
    {
        $stocks = Mockery::mock(StockService::class);
        $stocks->shouldReceive('refreshPrices')->once()->with(['HPG'], null, 25)->andReturnUsing(function () {
            $this->bar('HPG', '2026-09-18', 21.3);

            return ['stored' => 1, 'errors' => []];
        });
        $this->app->instance(StockService::class, $stocks);

        $this->get('/stock?symbol=HPG')->assertOk()->assertSee('"close":21.3', false)->assertDontSee('Chưa tải được dữ liệu giá');
    }

    #[Group('stockFreshness')]
    public function test_a_failed_first_fetch_shows_a_message_and_the_page_still_renders(): void
    {
        $stocks = Mockery::mock(StockService::class);
        $stocks->shouldReceive('refreshPrices')->once()->andReturn(['stored' => 0, 'errors' => ['HPG' => 'timed out']]);
        $this->app->instance(StockService::class, $stocks);

        $this->get('/stock?symbol=HPG')->assertOk()->assertSee('Chưa tải được dữ liệu giá cho HPG');
    }

    #[Group('stockFreshness')]
    public function test_unknown_and_malformed_symbols_never_reach_python_or_the_database(): void
    {
        $this->noPython();

        $this->get('/stock?symbol=NOPE')->assertOk()->assertSee('Không tìm thấy mã NOPE');
        $this->get('/stock?symbol=' . urlencode('FPT;rm -rf /'))->assertNotFound();
        $this->get('/stock?symbol=' . urlencode('<script>'))->assertNotFound();

        $this->assertSame(0, Stock::whereIn('symbol', ['NOPE'])->count());
        $this->assertSame(0, Stock::count());
    }

    #[Group('stockFreshness')]
    public function test_compare_data_applies_the_same_policy_and_appends_the_live_bar(): void
    {
        $this->noPython();
        $this->bar('FPT', '2026-09-17', 74.3);
        $this->bar('HPG', '2026-09-18', 21.0);
        $this->seedMarketSnapshot();

        $r = $this->getJson('/stock/compare-data?symbols=FPT,HPG,fpt,bad;sym');

        $r->assertOk()->assertJsonCount(2);
        $fpt = collect($r->json())->firstWhere('symbol', 'FPT');
        $this->assertCount(2, $fpt['prices']);                       // stored day + the snapshot's session
        $this->assertEqualsWithDelta(71.7, $fpt['latest_close'], 0.001);
        $this->assertEqualsWithDelta(-3.5, $fpt['change_percent'], 0.1);
    }
}
