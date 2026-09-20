<?php

namespace Tests\Unit\Frontend\Services;

use App\Frontend\Interfaces\PortfolioRepositoryInterface;
use App\Frontend\Interfaces\StockRepositoryInterface;
use App\Frontend\Services\PortfolioService;
use App\Models\Portfolio;
use App\Models\PortfolioItem;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests: no Laravel app is booted, repositories are mocked,
 * no database is touched. Covers only paths that don't call notify()
 * (which needs the container) — see tests/Feature/Frontend/Services
 * for the alert/notification paths.
 */
class PortfolioServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function makeItem(string $symbol, array $attrs = []): PortfolioItem
    {
        $item = new PortfolioItem(array_merge([
            'portfolio_id' => 10,
            'stock_symbol' => $symbol,
            'stock_name' => $symbol,
            'quantity' => 100,
            'buy_price' => 20,
            'current_price' => 20,
        ], $attrs));
        $item->id = 1;

        return $item;
    }

    #[Group('portfolioPrices')]
    public function test_update_portfolio_prices_uses_real_prices_from_stock_repository_not_random(): void
    {
        $item = $this->makeItem('ACB');
        $portfolio = new Portfolio(['user_id' => 1, 'name' => 'Test Portfolio']);
        $portfolio->id = 10;
        $portfolio->setRelation('items', collect([$item]));

        $portfolioRepo = \Mockery::mock(PortfolioRepositoryInterface::class);
        $stockRepo = \Mockery::mock(StockRepositoryInterface::class);

        $portfolioRepo->shouldReceive('findByIdAndUser')->once()->with(10, 1)->andReturn($portfolio);

        // The fix under test: PortfolioService must ask StockRepositoryInterface
        // for the latest synced price — it must NOT invent a random number.
        $stockRepo->shouldReceive('getLatestQuotes')->once()->with(['ACB'])->andReturn(['ACB' => ['close' => 25.5, 'prev_close' => null, 'date' => '2026-09-11']]);

        $portfolioRepo->shouldReceive('updateItemsPrices')
            ->once()
            ->with($portfolio, ['ACB' => ['price' => 25500.0, 'prev' => null, 'date' => '2026-09-11']])
            ->andReturn(true);

        $service = new PortfolioService($portfolioRepo, $stockRepo);

        $result = $service->updatePortfolioPrices(10, 1);

        $this->assertTrue($result);
    }

    #[Group('portfolioPrices')]
    public function test_update_portfolio_prices_returns_false_when_portfolio_not_found(): void
    {
        $portfolioRepo = \Mockery::mock(PortfolioRepositoryInterface::class);
        $stockRepo = \Mockery::mock(StockRepositoryInterface::class);

        $portfolioRepo->shouldReceive('findByIdAndUser')->once()->with(999, 1)->andReturn(null);
        $stockRepo->shouldNotReceive('getLatestQuotes');

        $service = new PortfolioService($portfolioRepo, $stockRepo);

        $this->assertFalse($service->updatePortfolioPrices(999, 1));
    }

    #[Group('portfolioPrices')]
    public function test_fetch_current_prices_skips_symbols_with_no_synced_price(): void
    {
        $itemWithPrice = $this->makeItem('ACB');
        $itemWithoutPrice = $this->makeItem('NEWIPO');
        $portfolio = new Portfolio(['user_id' => 1]);
        $portfolio->id = 10;
        $portfolio->setRelation('items', collect([$itemWithPrice, $itemWithoutPrice]));

        $portfolioRepo = \Mockery::mock(PortfolioRepositoryInterface::class);
        $stockRepo = \Mockery::mock(StockRepositoryInterface::class);

        $portfolioRepo->shouldReceive('findByIdAndUser')->once()->andReturn($portfolio);
        // NEWIPO has no synced StockPrice row yet, so the repository omits it from the map.
        $stockRepo->shouldReceive('getLatestQuotes')->once()->with(['ACB', 'NEWIPO'])->andReturn(['ACB' => ['close' => 25.5, 'prev_close' => null, 'date' => '2026-09-11']]);
        $portfolioRepo->shouldReceive('updateItemsPrices')->once()->with($portfolio, ['ACB' => ['price' => 25500.0, 'prev' => null, 'date' => '2026-09-11']])->andReturn(true);

        $service = new PortfolioService($portfolioRepo, $stockRepo);

        $this->assertTrue($service->updatePortfolioPrices(10, 1));
    }
}
