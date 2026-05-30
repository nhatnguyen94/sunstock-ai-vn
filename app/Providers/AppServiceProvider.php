<?php

/**
 * Author: Sun Nguyen
 * Email: nhat.nguyenminh94@gmail.com
 * Github: https://github.com/nhatnguyen94
 */

namespace App\Providers;

use App\Backend\Interfaces\NewsRepositoryInterface as BackendNewsRepositoryInterface;
use App\Backend\Interfaces\NewsServiceInterface as BackendNewsServiceInterface;
use App\Backend\Interfaces\StockRepositoryInterface as BackendStockRepositoryInterface;
use App\Backend\Interfaces\StockServiceInterface as BackendStockServiceInterface;
use App\Backend\Interfaces\UserRepositoryInterface as BackendUserRepositoryInterface;
use App\Backend\Interfaces\UserServiceInterface as BackendUserServiceInterface;
use App\Backend\Repositories\NewsRepository as BackendNewsRepository;
use App\Backend\Repositories\StockRepository as BackendStockRepository;
use App\Backend\Repositories\UserRepository as BackendUserRepository;
use App\Backend\Services\NewsService as BackendNewsService;
use App\Backend\Services\StockService as BackendStockService;
use App\Backend\Services\UserService as BackendUserService;
use App\Frontend\Interfaces\ExchangeRateRepositoryInterface;
use App\Frontend\Interfaces\CompanyFinancialRepositoryInterface;
use App\Frontend\Interfaces\NewsRepositoryInterface as FrontendNewsRepositoryInterface;
use App\Frontend\Interfaces\NewsServiceInterface;
use App\Frontend\Interfaces\PortfolioRepositoryInterface;
use App\Frontend\Interfaces\StockRepositoryInterface;
use App\Frontend\Interfaces\UserProfileRepositoryInterface;
use App\Frontend\Repositories\CompanyFinancialRepository;
use App\Frontend\Repositories\ExchangeRateRepository;
use App\Frontend\Repositories\NewsRepository as FrontendNewsRepository;
use App\Frontend\Repositories\PortfolioRepository;
use App\Frontend\Repositories\StockRepository;
use App\Frontend\Repositories\UserProfileRepository;
use App\Frontend\Services\NewsService;
use App\Models\Role;
use Illuminate\Pagination\Paginator;
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
            BackendUserRepositoryInterface::class,
            BackendUserRepository::class
        );
        $this->app->bind(
            BackendUserServiceInterface::class,
            BackendUserService::class
        );
        $this->app->bind(
            BackendNewsRepositoryInterface::class,
            BackendNewsRepository::class
        );
        $this->app->bind(
            BackendNewsServiceInterface::class,
            BackendNewsService::class
        );
        $this->app->bind(
            BackendStockRepositoryInterface::class,
            BackendStockRepository::class
        );
        $this->app->bind(
            BackendStockServiceInterface::class,
            BackendStockService::class
        );
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
            FrontendNewsRepositoryInterface::class,
            FrontendNewsRepository::class
        );
        $this->app->bind(
            UserProfileRepositoryInterface::class,
            UserProfileRepository::class
        );
        $this->app->bind(
            PortfolioRepositoryInterface::class,
            PortfolioRepository::class
        );
        $this->app->bind(
            CompanyFinancialRepositoryInterface::class,
            CompanyFinancialRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Use Bootstrap 5 pagination views (Tabler is built on Bootstrap 5)
        Paginator::useBootstrapFive();

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
