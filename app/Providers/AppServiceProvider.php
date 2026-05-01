<?php
/**
 * Author: Sun Nguyen
 * Email: nhat.nguyenminh94@gmail.com
 * Github: https://github.com/nhatnguyen94
 */
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Frontend\Interfaces\UserProfileRepositoryInterface;
use App\Frontend\Repositories\UserProfileRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Frontend\Interfaces\StockRepositoryInterface::class,
            \App\Frontend\Repositories\StockRepository::class
        );
        $this->app->bind(
            \App\Frontend\Interfaces\ExchangeRateRepositoryInterface::class,
            \App\Frontend\Repositories\ExchangeRateRepository::class
        );
        $this->app->bind(
            \App\Frontend\Interfaces\NewsServiceInterface::class,
            \App\Frontend\Services\NewsService::class
        );
        $this->app->bind(
            \App\Frontend\Interfaces\UserProfileRepositoryInterface::class, 
            \App\Frontend\Repositories\UserProfileRepository::class
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
