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
use App\Backend\Interfaces\ActivityLogRepositoryInterface;
use App\Backend\Repositories\ActivityLogRepository;
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
use App\Backend\Interfaces\RoleRepositoryInterface;
use App\Backend\Interfaces\RoleServiceInterface;
use App\Backend\Interfaces\PermissionRepositoryInterface;
use App\Backend\Interfaces\PermissionServiceInterface;
use App\Backend\Repositories\RoleRepository;
use App\Backend\Repositories\PermissionRepository;
use App\Backend\Services\RoleService;
use App\Backend\Services\PermissionService;
use App\Backend\Interfaces\QueueMonitorRepositoryInterface;
use App\Backend\Interfaces\QueueMonitorServiceInterface;
use App\Backend\Repositories\QueueMonitorRepository;
use App\Backend\Services\QueueMonitorService;
use App\Backend\Interfaces\NewsCategoryRepositoryInterface;
use App\Backend\Interfaces\NewsCategoryServiceInterface;
use App\Backend\Repositories\NewsCategoryRepository;
use App\Backend\Services\NewsCategoryService;
use App\Support\QueueJobLogger;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
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
        $this->app->bind(
            ActivityLogRepositoryInterface::class,
            ActivityLogRepository::class
        );
        $this->app->bind(
            RoleRepositoryInterface::class,
            RoleRepository::class
        );
        $this->app->bind(
            RoleServiceInterface::class,
            RoleService::class
        );
        $this->app->bind(
            PermissionRepositoryInterface::class,
            PermissionRepository::class
        );
        $this->app->bind(
            PermissionServiceInterface::class,
            PermissionService::class
        );
        $this->app->bind(
            QueueMonitorRepositoryInterface::class,
            QueueMonitorRepository::class
        );
        $this->app->bind(
            QueueMonitorServiceInterface::class,
            QueueMonitorService::class
        );
        $this->app->bind(
            NewsCategoryRepositoryInterface::class,
            NewsCategoryRepository::class
        );
        $this->app->bind(
            NewsCategoryServiceInterface::class,
            NewsCategoryService::class
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

        // Ghi log tiến trình xử lý job cho Admin > Giám sát Queue
        $this->registerQueueMonitoring();
    }

    /**
     * Ghi nhận job nào đang chạy / vừa chạy xong (real-time) cho trang
     * Giám sát Queue — xem App\Support\QueueJobLogger. Đăng ký ở đây (thay vì
     * EventServiceProvider, project này không có) vì đây là hạ tầng dùng
     * chung, không thuộc riêng Frontend hay Backend.
     */
    private function registerQueueMonitoring(): void
    {
        Queue::before(fn ($event) => QueueJobLogger::processing($event));
        Queue::after(fn ($event) => QueueJobLogger::processed($event));
        Queue::failing(fn ($event) => QueueJobLogger::failed($event));
    }

    /**
     * Định nghĩa nguồn kiểm tra quyền cho toàn bộ hệ thống.
     *
     * Thay vì hardcode từng Gate::define() cho từng ability (không scale
     * khi có thêm role/permission mới, mỗi lần thêm phải sửa code + deploy),
     * mọi ability được kiểm tra qua Gate::before() dựa trên bảng
     * permissions/role_permission trong DB. Admin tự tạo permission mới,
     * gán vào role qua Admin > Vai trò & Quyền hạn, rồi dùng
     * can:<permission-name> ở route/middleware/Blade — không cần sửa file
     * này nữa. Xem docs/RBAC.md.
     */
    private function defineGates(): void
    {
        Gate::before(function ($user, string $ability) {
            return $user->hasPermission($ability);
        });
    }
}
