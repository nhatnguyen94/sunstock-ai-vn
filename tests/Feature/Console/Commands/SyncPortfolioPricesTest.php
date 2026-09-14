<?php

namespace Tests\Feature\Console\Commands;

use App\Frontend\Services\PortfolioService;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Boots Laravel to use $this->artisan() against the real Artisan kernel.
 * PortfolioService is swapped for a mock in the container, so no
 * repository/database code runs.
 */
class SyncPortfolioPricesTest extends TestCase
{
    #[Group('portfolio-alerts')]
    public function test_command_refreshes_portfolios_and_reports_the_count(): void
    {
        $mock = \Mockery::mock(PortfolioService::class);
        $mock->shouldReceive('refreshAllPortfolioPrices')->once()->andReturn(3);
        $this->app->instance(PortfolioService::class, $mock);

        $this->artisan('sync:portfolio-prices')
            ->expectsOutputToContain('Refreshed 3 portfolio(s)')
            ->assertExitCode(0);
    }

    #[Group('portfolio-alerts')]
    public function test_command_reports_zero_when_no_portfolios_need_refreshing(): void
    {
        $mock = \Mockery::mock(PortfolioService::class);
        $mock->shouldReceive('refreshAllPortfolioPrices')->once()->andReturn(0);
        $this->app->instance(PortfolioService::class, $mock);

        $this->artisan('sync:portfolio-prices')
            ->expectsOutputToContain('Refreshed 0 portfolio(s)')
            ->assertExitCode(0);
    }
}
