<?php

namespace Tests\Feature\Frontend\Services;

use App\Frontend\Services\StockService;
use App\Jobs\RefreshStockPricesJob;
use App\Models\Stock;
use App\Models\StockPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Storing what get_stock.py returns, and the refresh entry points built on it. The Python boundary
 * (fetchStockDataFromPython) is stubbed; the upsert runs against the real DB.
 */
class StockServicePriceRefreshTest extends TestCase
{
    use RefreshDatabase;

    private function service(?array $fetchResult = null, ?callable $expectArgs = null): StockService
    {
        $mock = Mockery::mock(StockService::class)->makePartial();
        if ($fetchResult !== null) {
            $expectation = $mock->shouldReceive('fetchStockDataFromPython')->once();
            if ($expectArgs) {
                $expectArgs($expectation);
            }
            $expectation->andReturn($fetchResult);
        }

        return $mock;
    }

    private function bar(string $date, float $close, array $over = []): array
    {
        return $over + ['time' => strtotime($date . ' 07:00:00 UTC') * 1000, 'date' => $date, 'open' => $close, 'high' => $close + 1, 'low' => $close - 1, 'close' => $close, 'volume' => 1000];
    }

    #[Group('stockFreshness')]
    public function test_store_price_data_upserts_bars_by_stock_and_date(): void
    {
        $fpt = Stock::create(['symbol' => 'FPT']);

        $n = $this->service()->storePriceData(['FPT' => [$this->bar('2026-09-17', 74.3), $this->bar('2026-09-18', 71.7)]], ['FPT' => $fpt->id]);
        $again = $this->service()->storePriceData(['FPT' => [$this->bar('2026-09-18', 72.0)]], ['FPT' => $fpt->id]);   // corrected close

        $this->assertSame(2, $n);
        $this->assertSame(1, $again);
        $this->assertSame(2, StockPrice::where('stock_id', $fpt->id)->count());                       // no duplicate day
        $this->assertEqualsWithDelta(72.0, (float) StockPrice::where('stock_id', $fpt->id)->orderByDesc('date')->value('close'), 0.001);
    }

    #[Group('stockFreshness')]
    public function test_store_price_data_derives_the_date_from_epoch_ms_and_skips_unknown_symbols_and_bad_bars(): void
    {
        $fpt = Stock::create(['symbol' => 'FPT']);
        $noDate = $this->bar('2026-09-18', 71.7);
        unset($noDate['date']);                                   // old script output: only `time`

        $n = $this->service()->storePriceData([
            'FPT' => [$noDate, ['time' => 1, 'close' => null], ['no' => 'bar']],
            'NOPE' => [$this->bar('2026-09-18', 1.0)],            // not tracked
        ], ['FPT' => $fpt->id]);

        $this->assertSame(1, $n);
        $this->assertSame('2026-09-18', substr((string) StockPrice::where('stock_id', $fpt->id)->value('date'), 0, 10));
        $this->assertSame(0, StockPrice::where('stock_id', '!=', $fpt->id)->count());
    }

    #[Group('stockFreshness')]
    public function test_refresh_prices_asks_for_the_missing_range_with_the_given_timeout_and_stores_the_answer(): void
    {
        $fpt = Stock::create(['symbol' => 'FPT']);
        $svc = $this->service(
            ['data' => ['FPT' => [$this->bar('2026-09-18', 71.7)]], 'errors' => [], 'sources' => ['FPT' => 'KBS-direct']],
            fn ($e) => $e->with('FPT,VNM', 25, '2026-09-06')
        );

        $r = $svc->refreshPrices(['FPT', 'VNM', 'FPT', 'bad;sym'], '2026-09-06', 25);

        $this->assertSame(1, $r['stored']);
        $this->assertSame([], $r['errors']);
        $this->assertSame(1, StockPrice::where('stock_id', $fpt->id)->count());
    }

    #[Group('stockFreshness')]
    public function test_refresh_prices_reports_per_symbol_errors_and_hard_failures(): void
    {
        Stock::create(['symbol' => 'FPT']);

        $partial = $this->service(['data' => [], 'errors' => ['FPT' => 'KBS-direct: timed out']])->refreshPrices(['FPT']);
        $this->assertSame(0, $partial['stored']);
        $this->assertSame('KBS-direct: timed out', $partial['errors']['FPT']);

        $hard = $this->service(['error' => 'Lỗi khi gọi script Python'])->refreshPrices(['FPT']);
        $this->assertSame('Lỗi khi gọi script Python', $hard['errors']['*']);

        $none = $this->service()->refreshPrices(['bad;sym', '']);
        $this->assertArrayHasKey('*', $none['errors']);
    }

    // ── the background job ──────────────────────────────────────────────────

    #[Group('stockFreshness')]
    public function test_the_job_refreshes_only_what_it_was_given_and_names_itself_for_the_queue_monitor(): void
    {
        $stocks = Mockery::mock(StockService::class);
        $stocks->shouldReceive('refreshPrices')->once()->with(['FPT'], '2026-09-06', 60)->andReturn(['stored' => 5, 'errors' => []]);

        $job = new RefreshStockPricesJob(['FPT'], '2026-09-06');
        $job->handle($stocks);

        $this->assertStringContainsString('FPT', $job->queueSummary());
        $this->assertStringContainsString('2026-09-06', $job->queueSummary());
    }

    #[Group('stockFreshness')]
    public function test_a_window_with_no_new_session_is_not_a_failure_but_a_hard_error_is_retried(): void
    {
        $quiet = Mockery::mock(StockService::class);
        $quiet->shouldReceive('refreshPrices')->once()->andReturn(['stored' => 0, 'errors' => []]);
        (new RefreshStockPricesJob(['FPT'], '2026-09-06'))->handle($quiet);            // must not throw

        $broken = Mockery::mock(StockService::class);
        $broken->shouldReceive('refreshPrices')->once()->andReturn(['stored' => 0, 'errors' => ['*' => 'script died']]);
        $this->expectException(\RuntimeException::class);
        (new RefreshStockPricesJob(['FPT']))->handle($broken);
    }
}
