<?php

/**
 * Author: Sun Nguyen
 * Email: nhat.nguyenminh94@gmail.com
 * Github: https://github.com/nhatnguyen94
 */

namespace App\Providers;

use App\Frontend\Interfaces\ExchangeRateRepositoryInterface;
use App\Frontend\Interfaces\NewsServiceInterface;
use App\Frontend\Interfaces\PortfolioRepositoryInterface;
use App\Frontend\Interfaces\StockRepositoryInterface;
use App\Frontend\Interfaces\UserProfileRepositoryInterface;
use App\Frontend\Repositories\ExchangeRateRepository;
use App\Frontend\Repositories\PortfolioRepository;
use App\Frontend\Repositories\StockRepository;
use App\Frontend\Repositories\UserProfileRepository;
use App\Frontend\Services\NewsService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            StockRepositoryInterface::class,
            StockRepository::class
        );
        $this->app->bind(
            ExchangeRateRepositoryInterface::class,
            ExchangeRateRepository::class
        );
        $this->app->bind(
            NewsServiceInterface::class,
            NewsService::class
        );
        $this->app->bind(
            UserProfileRepositoryInterface::class,
            UserProfileRepository::class
        );
        $this->app->bind(
            PortfolioRepositoryInterface::class,
            PortfolioRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
