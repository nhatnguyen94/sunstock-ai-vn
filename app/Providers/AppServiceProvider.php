<?php

/**
 * Author: Sun Nguyen
 * Email: nhat.nguyenminh94@gmail.com
 * Github: https://github.com/nhatnguyen94
 */

namespace App\Providers;

use App\Backend\Interfaces\ActivityLogRepositoryInterface;
use App\Backend\Interfaces\NewsCategoryRepositoryInterface;
use App\Backend\Interfaces\NewsCategoryServiceInterface;
use App\Backend\Interfaces\NewsRepositoryInterface as BackendNewsRepositoryInterface;
use App\Backend\Interfaces\NewsServiceInterface as BackendNewsServiceInterface;
use App\Backend\Interfaces\PermissionRepositoryInterface;
use App\Backend\Interfaces\PermissionServiceInterface;
use App\Backend\Interfaces\QueueMonitorRepositoryInterface;
use App\Backend\Interfaces\QueueMonitorServiceInterface;
use App\Backend\Interfaces\RoleRepositoryInterface;
use App\Backend\Interfaces\RoleServiceInterface;
use App\Backend\Interfaces\StockRepositoryInterface as BackendStockRepositoryInterface;
use App\Backend\Interfaces\StockServiceInterface as BackendStockServiceInterface;
use App\Backend\Interfaces\UserRepositoryInterface as BackendUserRepositoryInterface;
use App\Backend\Interfaces\UserServiceInterface as BackendUserServiceInterface;
use App\Backend\Repositories\ActivityLogRepository;
use App\Backend\Repositories\NewsCategoryRepository;
use App\Backend\Repositories\NewsRepository as BackendNewsRepository;
use App\Backend\Repositories\PermissionRepository;
use App\Backend\Repositories\QueueMonitorRepository;
use App\Backend\Repositories\RoleRepository;
use App\Backend\Repositories\StockRepository as BackendStockRepository;
use App\Backend\Repositories\UserRepository as BackendUserRepository;
use App\Backend\Services\NewsCategoryService;
use App\Backend\Services\NewsService as BackendNewsService;
use App\Backend\Services\PermissionService;
use App\Backend\Services\QueueMonitorService;
use App\Backend\Services\RoleService;
use App\Backend\Services\StockService as BackendStockService;
use App\Backend\Services\UserService as BackendUserService;
use App\Frontend\Interfaces\CompanyFinancialRepositoryInterface;
use App\Frontend\Interfaces\CompanyProfileRepositoryInterface;
use App\Frontend\Interfaces\EtfRepositoryInterface;
use App\Frontend\Interfaces\ExchangeRateRepositoryInterface;
use App\Frontend\Interfaces\FundRepositoryInterface;
use App\Frontend\Interfaces\GoldPriceRepositoryInterface;
use App\Frontend\Interfaces\MarketSnapshotRepositoryInterface;
use App\Frontend\Interfaces\NewsRepositoryInterface as FrontendNewsRepositoryInterface;
use App\Frontend\Interfaces\NewsServiceInterface;
use App\Frontend\Interfaces\PortfolioRepositoryInterface;
use App\Frontend\Interfaces\PortfolioTransactionRepositoryInterface;
use App\Frontend\Interfaces\StockRepositoryInterface;
use App\Frontend\Interfaces\UserProfileRepositoryInterface;
use App\Frontend\Interfaces\WatchlistRepositoryInterface;
use App\Frontend\Repositories\CompanyFinancialRepository;
use App\Frontend\Repositories\CompanyProfileRepository;
use App\Frontend\Repositories\EtfRepository;
use App\Frontend\Repositories\ExchangeRateRepository;
use App\Frontend\Repositories\FundRepository;
use App\Frontend\Repositories\GoldPriceRepository;
use App\Frontend\Repositories\MarketSnapshotRepository;
use App\Frontend\Repositories\NewsRepository as FrontendNewsRepository;
use App\Frontend\Repositories\PortfolioRepository;
use App\Frontend\Repositories\PortfolioTransactionRepository;
use App\Frontend\Repositories\StockRepository;
use App\Frontend\Repositories\UserProfileRepository;
use App\Frontend\Repositories\WatchlistRepository;
use App\Frontend\Services\CompanyProfileService;
use App\Frontend\Services\NewsService;
use App\Frontend\Services\PortfolioLedgerService;
use App\Frontend\Services\PortfolioService;
use App\Support\LoginAuditor;
use App\Support\QueueJobLogger;
use App\Support\SyncRunRecorder;
use App\Support\TransformerResponse;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
            CompanyProfileRepositoryInterface::class,
            CompanyProfileRepository::class
        );
        $this->app->bind(
            FundRepositoryInterface::class,
            FundRepository::class
        );
        $this->app->bind(
            EtfRepositoryInterface::class,
            EtfRepository::class
        );
        $this->app->bind(
            GoldPriceRepositoryInterface::class,
            GoldPriceRepository::class
        );
        $this->app->bind(
            MarketSnapshotRepositoryInterface::class,
            MarketSnapshotRepository::class
        );
        $this->app->bind(
            PortfolioTransactionRepositoryInterface::class,
            PortfolioTransactionRepository::class
        );
        // Laravel leaves `?Service $x = null` constructor parameters at their default (null) instead of resolving
        // them, so the optional collaborators are handed over explicitly. Unit tests keep building the service with
        // just the two repositories.
        $this->app->bind(PortfolioService::class, fn ($app) => new PortfolioService(
            $app->make(PortfolioRepositoryInterface::class),
            $app->make(StockRepositoryInterface::class),
            $app->make(CompanyProfileService::class),
            $app->make(PortfolioLedgerService::class)
        ));
        $this->app->bind(
            WatchlistRepositoryInterface::class,
            WatchlistRepository::class
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

        // "2 giờ trước" instead of "2 hours ago" in every diffForHumans() (admin and frontend)
        Carbon::setLocale('vi');

        // Định nghĩa Gates cho phân quyền
        $this->defineGates();

        $this->pinRootUrl();
        $this->defineAuthRateLimiters();
        $this->defineAiRateLimiters();

        // Ghi log tiến trình xử lý job cho Admin > Giám sát Queue
        $this->registerQueueMonitoring();

        // Lịch sử chạy của mọi lệnh sync:* / signals:* (Admin > Sync Status), dù do scheduler, nút "Sync ngay" hay terminal chạy
        $this->app->singleton(SyncRunRecorder::class);
        Event::listen(CommandStarting::class, [SyncRunRecorder::class, 'starting']);
        Event::listen(CommandFinished::class, [SyncRunRecorder::class, 'finished']);

        // Lịch sử đăng nhập cho Admin > Bảo mật (cổng thường và /admin)
        Event::listen(Login::class, [LoginAuditor::class, 'succeeded']);
        Event::listen(Failed::class, [LoginAuditor::class, 'failed']);
    }

    /**
     * Every generated URL (password-reset and verification links in e-mails above all) is built from APP_URL, never
     * from the request's Host header. Otherwise anyone can POST /forgot-password for a victim with
     * "Host: evil.example" and the victim receives a genuine reset token inside a link to evil.example.
     */
    private function pinRootUrl(): void
    {
        $root = (string) config('app.url');
        if ($root === '') {
            return;
        }

        URL::forceRootUrl($root);
        if (str_starts_with($root, 'https://')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Brute-force limits for the credential endpoints. They apply to the POSTs only (viewing a form is harmless and
     * used to burn the same 5-a-minute budget), and are keyed three ways:
     *  - per IP: one machine trying many accounts;
     *  - per account + IP: one machine hammering one account (the classic 5 tries a minute);
     *  - per account across all IPs, hourly: a botnet spreading guesses over many addresses.
     */
    private function defineAuthRateLimiters(): void
    {
        $email = fn (Request $r) => Str::lower(is_string($r->input('email')) ? trim($r->input('email')) : '');

        RateLimiter::for('auth-login', fn (Request $r) => [
            Limit::perMinute(20)->by('login-ip:'.$r->ip()),
            Limit::perMinute(5)->by('login-acct-ip:'.$email($r).'|'.$r->ip()),
            Limit::perHour(30)->by('login-acct:'.$email($r)),
        ]);

        RateLimiter::for('auth-register', fn (Request $r) => [
            Limit::perMinute(5)->by('register-ip:'.$r->ip()),
            Limit::perHour(20)->by('register-ip-hour:'.$r->ip()),
        ]);

        // Each request sends an e-mail: also cap per recipient so nobody can flood a victim's inbox.
        RateLimiter::for('auth-reset-request', fn (Request $r) => [
            Limit::perMinute(3)->by('reset-req-ip:'.$r->ip()),
            Limit::perHour(5)->by('reset-req-acct:'.$email($r)),
        ]);

        RateLimiter::for('auth-reset', fn (Request $r) => [
            Limit::perMinute(5)->by('reset-ip:'.$r->ip()),
            Limit::perHour(10)->by('reset-acct:'.$email($r)),
        ]);
    }

    /**
     * AI chat and market prediction: signed-in users only, limited PER ACCOUNT (config/ai_limits.php, tuned from
     * .env). Prediction is one request per interval; chat is N questions per window. The 429 body carries a message
     * the front end shows as is.
     */
    private function defineAiRateLimiters(): void
    {
        $key = fn (Request $r, string $name) => $name.':'.($r->user()?->getAuthIdentifier() ?? $r->ip());

        RateLimiter::for('ai-predict', function (Request $r) use ($key) {
            $minutes = (int) config('ai_limits.predict.interval_minutes');

            return Limit::perMinutes($minutes, 1)->by($key($r, 'ai-predict'))->response(function (Request $request, array $headers) use ($minutes) {
                $retry = (int) ($headers['Retry-After'] ?? 60);
                $wait = max(1, (int) ceil($retry / 60));

                return TransformerResponse::tooManyRequests("Mỗi tài khoản chỉ được dự đoán 1 lần mỗi {$minutes} phút. Vui lòng thử lại sau khoảng {$wait} phút.", $retry, ['error' => true])->withHeaders($headers);
            });
        });

        RateLimiter::for('ai-chat', function (Request $r) use ($key) {
            $minutes = (int) config('ai_limits.chat.window_minutes');
            $max = (int) config('ai_limits.chat.max_questions');

            return Limit::perMinutes($minutes, $max)->by($key($r, 'ai-chat'))->response(function (Request $request, array $headers) use ($minutes, $max) {
                $retry = (int) ($headers['Retry-After'] ?? 60);
                $wait = max(1, (int) ceil($retry / 60));

                return TransformerResponse::tooManyRequests("Bạn đã hỏi tối đa {$max} câu trong {$minutes} phút. Vui lòng thử lại sau khoảng {$wait} phút.", $retry, ['error' => true])->withHeaders($headers);
            });
        });
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
