<?php
/**
 * Author: Sun Nguyen
 * Email: nhat.nguyenminh94@gmail.com
 * Github: https://github.com/nhatnguyen94
 */
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Interfaces\StockRepositoryInterface::class,
            \App\Repositories\StockRepository::class
        );
        $this->app->bind(
            \App\Interfaces\ExchangeRateRepositoryInterface::class,
            \App\Repositories\ExchangeRateRepository::class
        );
        $this->app->bind(
            \App\Interfaces\NewsServiceInterface::class,
            \App\Services\NewsService::class
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
