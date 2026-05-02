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
use App\Models\Role;
use Illuminate\Support\Facades\Gate;
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
        // Định nghĩa Gates cho phân quyền
        $this->defineGates();
    }

    /**
     * Định nghĩa các Gates cho hệ thống phân quyền
     */
    private function defineGates(): void
    {
        // Gate manage-users: chỉ Admin mới có quyền quản lý users
        Gate::define('manage-users', function ($user) {
            return $user->hasRole(Role::ADMIN);
        });

        // Gate manage-features: Admin và AdminSupport có quyền quản lý tính năng
        Gate::define('manage-features', function ($user) {
            return $user->hasAnyRole([Role::ADMIN, Role::ADMIN_SUPPORT]);
        });

        // Gate view-timeline: Admin, Webadmin, AdminSupport đều có quyền xem timeline
        Gate::define('view-timeline', function ($user) {
            return $user->hasAnyRole([Role::ADMIN, Role::WEBADMIN, Role::ADMIN_SUPPORT]);
        });

        // Gate access-backend: kiểm tra quyền truy cập backend chung
        Gate::define('access-backend', function ($user) {
            return $user->canAccessBackend();
        });
    }
}
