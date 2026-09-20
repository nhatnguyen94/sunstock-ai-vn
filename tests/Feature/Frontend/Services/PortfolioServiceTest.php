<?php

namespace Tests\Feature\Frontend\Services;

use App\Frontend\Interfaces\PortfolioRepositoryInterface;
use App\Frontend\Interfaces\StockRepositoryInterface;
use App\Frontend\Services\PortfolioService;
use App\Models\Portfolio;
use App\Models\PortfolioItem;
use App\Models\User;
use App\Notifications\PortfolioAlertNotification;
use Illuminate\Support\Facades\Notification;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * "Feature" only because notify() needs a booted Laravel container
 * (Notification::fake() swaps the driver at the container level).
 * Repositories are still mocked — no real database is touched.
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

    private function makePortfolio(array $items, int $id = 10, int $userId = 1): Portfolio
    {
        $portfolio = new Portfolio(['user_id' => $userId, 'name' => 'Test Portfolio']);
        $portfolio->id = $id;
        $portfolio->user_id = $userId;
        $portfolio->setRelation('items', collect($items));
        $portfolio->setRelation('user', new User(['name' => 'Test User', 'email' => "user{$userId}@example.com"]));

        return $portfolio;
    }

    #[Group('portfolioAlerts')]
    public function test_notification_sent_once_when_item_crosses_target_price(): void
    {
        Notification::fake();

        $item = $this->makeItem('ACB', ['current_price' => 25, 'target_price' => 20, 'target_alerted_at' => null]);
        $portfolio = $this->makePortfolio([$item]);

        $portfolioRepo = \Mockery::mock(PortfolioRepositoryInterface::class);
        $stockRepo = \Mockery::mock(StockRepositoryInterface::class);

        $portfolioRepo->shouldReceive('findByIdAndUser')->once()->andReturn($portfolio);
        $stockRepo->shouldReceive('getLatestQuotes')->once()->andReturn(['ACB' => ['close' => 25.0, 'prev_close' => null, 'date' => '2026-09-11']]);
        $portfolioRepo->shouldReceive('updateItemsPrices')->once()->andReturn(true);
        $portfolioRepo->shouldReceive('setAlertFlag')->once()
            ->with($item, 'target_alerted_at', \Mockery::type(\DateTimeInterface::class));

        $service = new PortfolioService($portfolioRepo, $stockRepo);
        $service->updatePortfolioPrices(10, 1);

        Notification::assertSentTo($portfolio->user, PortfolioAlertNotification::class);
    }

    #[Group('portfolioAlerts')]
    public function test_notification_not_resent_while_still_above_target(): void
    {
        Notification::fake();

        // target_alerted_at already set => already notified for this crossing.
        $item = $this->makeItem('ACB', [
            'current_price' => 25,
            'target_price' => 20,
            'target_alerted_at' => now(),
        ]);
        $portfolio = $this->makePortfolio([$item]);

        $portfolioRepo = \Mockery::mock(PortfolioRepositoryInterface::class);
        $stockRepo = \Mockery::mock(StockRepositoryInterface::class);

        $portfolioRepo->shouldReceive('findByIdAndUser')->once()->andReturn($portfolio);
        $stockRepo->shouldReceive('getLatestQuotes')->once()->andReturn(['ACB' => ['close' => 26.0, 'prev_close' => null, 'date' => '2026-09-11']]);
        $portfolioRepo->shouldReceive('updateItemsPrices')->once()->andReturn(true);
        $portfolioRepo->shouldNotReceive('setAlertFlag');

        $service = new PortfolioService($portfolioRepo, $stockRepo);
        $service->updatePortfolioPrices(10, 1);

        Notification::assertNothingSent();
    }

    #[Group('portfolioAlerts')]
    public function test_alert_flag_resets_when_price_moves_back_below_target(): void
    {
        Notification::fake();

        $item = $this->makeItem('ACB', [
            'current_price' => 25,
            'target_price' => 30,
            'target_alerted_at' => now(), // was alerted previously, price has since dropped back
        ]);
        $portfolio = $this->makePortfolio([$item]);

        $portfolioRepo = \Mockery::mock(PortfolioRepositoryInterface::class);
        $stockRepo = \Mockery::mock(StockRepositoryInterface::class);

        $portfolioRepo->shouldReceive('findByIdAndUser')->once()->andReturn($portfolio);
        $stockRepo->shouldReceive('getLatestQuotes')->once()->andReturn(['ACB' => ['close' => 22.0, 'prev_close' => null, 'date' => '2026-09-11']]);
        $portfolioRepo->shouldReceive('updateItemsPrices')->once()->andReturn(true);
        $portfolioRepo->shouldReceive('setAlertFlag')->once()->with($item, 'target_alerted_at', null);

        $service = new PortfolioService($portfolioRepo, $stockRepo);
        $service->updatePortfolioPrices(10, 1);

        Notification::assertNothingSent();
    }

    #[Group('portfolioAlerts')]
    public function test_notification_sent_when_item_crosses_stop_loss_price(): void
    {
        Notification::fake();

        $item = $this->makeItem('ACB', ['current_price' => 15, 'stop_loss_price' => 18, 'stop_loss_alerted_at' => null]);
        $portfolio = $this->makePortfolio([$item]);

        $portfolioRepo = \Mockery::mock(PortfolioRepositoryInterface::class);
        $stockRepo = \Mockery::mock(StockRepositoryInterface::class);

        $portfolioRepo->shouldReceive('findByIdAndUser')->once()->andReturn($portfolio);
        $stockRepo->shouldReceive('getLatestQuotes')->once()->andReturn(['ACB' => ['close' => 15.0, 'prev_close' => null, 'date' => '2026-09-11']]);
        $portfolioRepo->shouldReceive('updateItemsPrices')->once()->andReturn(true);
        $portfolioRepo->shouldReceive('setAlertFlag')->once()
            ->with($item, 'stop_loss_alerted_at', \Mockery::type(\DateTimeInterface::class));

        $service = new PortfolioService($portfolioRepo, $stockRepo);
        $service->updatePortfolioPrices(10, 1);

        Notification::assertSentTo($portfolio->user, PortfolioAlertNotification::class);
    }

    #[Group('portfolioAlerts')]
    public function test_refresh_all_portfolio_prices_iterates_every_active_portfolio(): void
    {
        Notification::fake();

        $itemA = $this->makeItem('ACB');
        $itemB = $this->makeItem('FPT');
        $portfolioA = $this->makePortfolio([$itemA], id: 1, userId: 1);
        $portfolioB = $this->makePortfolio([$itemB], id: 2, userId: 2);

        $portfolioRepo = \Mockery::mock(PortfolioRepositoryInterface::class);
        $stockRepo = \Mockery::mock(StockRepositoryInterface::class);

        $portfolioRepo->shouldReceive('getAllActivePortfolios')->once()
            ->andReturn(new \Illuminate\Database\Eloquent\Collection([$portfolioA, $portfolioB]));
        $portfolioRepo->shouldReceive('findByIdAndUser')->once()->with(1, 1)->andReturn($portfolioA);
        $portfolioRepo->shouldReceive('findByIdAndUser')->once()->with(2, 2)->andReturn($portfolioB);
        $stockRepo->shouldReceive('getLatestQuotes')->twice()->andReturn([]);
        $portfolioRepo->shouldReceive('updateItemsPrices')->twice()->andReturn(true);

        $service = new PortfolioService($portfolioRepo, $stockRepo);

        $this->assertSame(2, $service->refreshAllPortfolioPrices());
        Notification::assertNothingSent();
    }
}
