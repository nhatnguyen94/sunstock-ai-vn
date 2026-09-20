<?php

namespace Tests\Feature\Frontend\Repositories;

use App\Frontend\Repositories\StockRepository;
use App\Models\Stock;
use App\Models\StockPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** Real SQL: latest/previous close per symbol, history window, tracking of symbols without data. */
class StockRepositoryQuotesTest extends TestCase
{
    use RefreshDatabase;

    private function repo(): StockRepository
    {
        return $this->app->make(StockRepository::class);
    }

    private function prices(string $symbol, array $closesByDate): Stock
    {
        $stock = Stock::create(['symbol' => $symbol]);
        foreach ($closesByDate as $date => $close) {
            StockPrice::create(['stock_id' => $stock->id, 'date' => $date, 'open' => $close, 'high' => $close, 'low' => $close, 'close' => $close, 'volume' => 1]);
        }

        return $stock;
    }

    #[Group('portfolioPrices')]
    public function test_latest_quotes_return_the_last_two_sessions_per_symbol(): void
    {
        $this->prices('AAA', ['2026-09-09' => 10.0, '2026-09-10' => 11.0, '2026-09-11' => 12.0]);
        $this->prices('BBB', ['2026-09-11' => 50.0]);   // a single session: no previous close

        $quotes = $this->repo()->getLatestQuotes(['AAA', 'BBB', 'NOPE']);

        $this->assertSame(['close' => 12.0, 'prev_close' => 11.0, 'date' => '2026-09-11'], $quotes['AAA']);
        $this->assertSame(['close' => 50.0, 'prev_close' => null, 'date' => '2026-09-11'], $quotes['BBB']);
        $this->assertArrayNotHasKey('NOPE', $quotes);
        $this->assertSame([], $this->repo()->getLatestQuotes([]));
    }

    #[Group('portfolioPrices')]
    public function test_close_history_is_windowed_per_symbol_and_ascending(): void
    {
        $this->prices('AAA', ['2026-09-11' => 12.0, '2026-09-09' => 10.0, '2026-09-10' => 11.0]);
        $this->prices('BBB', ['2026-09-10' => 50.0]);

        $history = $this->repo()->getCloseHistory(['AAA', 'BBB'], '2026-09-10');

        $this->assertSame(['2026-09-10' => 11.0, '2026-09-11' => 12.0], $history['AAA']);
        $this->assertSame(['2026-09-10' => 50.0], $history['BBB']);
    }

    #[Group('portfolioPrices')]
    public function test_ensure_tracked_creates_missing_stocks_and_reports_those_without_any_price(): void
    {
        $this->prices('HASDATA', ['2026-09-11' => 10.0]);

        $missing = $this->repo()->ensureTracked(['HASDATA', 'FRESH', 'FRESH']);

        $this->assertSame(['FRESH'], $missing);
        $this->assertNotNull(Stock::where('symbol', 'FRESH')->first());   // now tracked, so sync:stock-prices will pick it up too
    }
}
