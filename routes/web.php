<?php

use App\Backend\Controllers\AccountController;
use App\Backend\Controllers\AdminAuthController;
use App\Backend\Controllers\DashboardController;
use App\Backend\Controllers\NewsCategoryController;
use App\Backend\Controllers\NewsController;
use App\Backend\Controllers\PermissionController;
use App\Backend\Controllers\PortfolioController as AdminPortfolioController;
use App\Backend\Controllers\QueueMonitorController;
use App\Backend\Controllers\RoleController;
use App\Backend\Controllers\StockController as AdminStockController;
use App\Backend\Controllers\SyncStatusController;
use App\Backend\Controllers\TimelineController;
use App\Backend\Controllers\UserController;
use App\Frontend\Controllers\AiController;
use App\Frontend\Controllers\AuthController;
use App\Frontend\Controllers\CompanyProfileController;
use App\Frontend\Controllers\EmailVerificationController;
use App\Frontend\Controllers\ExchangeRateController;
use App\Frontend\Controllers\FundController;
use App\Frontend\Controllers\GoldPriceController;
use App\Frontend\Controllers\NewsController as FrontendNewsController;
use App\Frontend\Controllers\PasswordResetController;
use App\Frontend\Controllers\PortfolioController;
use App\Frontend\Controllers\ProfileController;
use App\Frontend\Controllers\StockController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StockController::class, 'home'])->name('home');

// Data-heavy / Python-backed endpoints — throttled to prevent scraping & subprocess exhaustion
Route::middleware('throttle:30,1')->group(function () {
    Route::post('/search', [StockController::class, 'search'])->name('stock.search');

    Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('/stock/compare', [StockController::class, 'compare'])->name('stock.compare');
    Route::get('/stock/compare-data', [StockController::class, 'compareData'])->name('stock.compare-data');
    Route::get('/stock/finance', [StockController::class, 'finance'])->name('stock.finance');
    Route::get('/stock/screener', [StockController::class, 'screener'])->name('stock.screener');

    Route::get('/stocks-list', [StockController::class, 'getStockSymbols']);

    // Company profile (vnstock Company API, DB-cached): shareholders, officers, subsidiaries, events
    Route::get('/company/{symbol}', [CompanyProfileController::class, 'show'])
        ->where('symbol', '[A-Za-z0-9]{2,10}')->name('company.show');
    Route::post('/company/{symbol}/load', [CompanyProfileController::class, 'load'])
        ->where('symbol', '[A-Za-z0-9]{2,10}')->name('company.load');

    // Gold prices (SJC / BTMC / world) — sits with the exchange rate under the "Thị trường" menu
    Route::get('/gold', [GoldPriceController::class, 'index'])->name('gold.index');
    Route::get('/gold/history/{id}', [GoldPriceController::class, 'history'])->whereNumber('id')->name('gold.history');
    Route::post('/gold/refresh', [GoldPriceController::class, 'refresh'])->name('gold.refresh');

    // Open-ended fund catalog (vnstock Fund API / Fmarket). /funds/compare must stay above /funds/{code}.
    Route::get('/funds', [FundController::class, 'index'])->name('funds.index');
    Route::get('/funds/compare', [FundController::class, 'compare'])->name('funds.compare');
    Route::get('/funds/{code}', [FundController::class, 'show'])
        ->where('code', '[A-Za-z0-9._-]{2,40}')->name('funds.show');
    Route::get('/funds/{code}/detail', [FundController::class, 'detail'])
        ->where('code', '[A-Za-z0-9._-]{2,40}')->name('funds.detail');

    Route::get('/exchange-rate', [ExchangeRateController::class, 'index'])->name('exchange-rate.index');
    Route::get('/exchange-rate/search', [ExchangeRateController::class, 'search'])->name('exchange-rate.search');
});

// News routes
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/news', [FrontendNewsController::class, 'index'])->name('news.index');
    Route::get('/news/category/{categorySlug}', [FrontendNewsController::class, 'index'])->name('news.category');
});

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/ai-chat', [StockController::class, 'aiChat']);
    Route::post('/ai-predict', [AiController::class, 'predict']);
});

Route::middleware('throttle:5,1')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    // Password reset
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Email Verification routes
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:3,1')
        ->name('verification.send');
});
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['auth', 'signed'])->name('verification.verify');

// Profile routes (requires authentication and verified email)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Portfolio routes
    Route::get('/portfolio', [PortfolioController::class, 'index'])->name('portfolio.index');
    Route::get('/portfolio/create', [PortfolioController::class, 'create'])->name('portfolio.create');
    Route::post('/portfolio', [PortfolioController::class, 'store'])->name('portfolio.store');
    // Fixed paths must be declared before /portfolio/{id}
    Route::get('/portfolio/add', [PortfolioController::class, 'quickAdd'])->name('portfolio.quick-add');
    Route::get('/portfolio/quote/{symbol}', [PortfolioController::class, 'quote'])
        ->where('symbol', '[A-Za-z0-9]{2,10}')->name('portfolio.quote');
    Route::get('/portfolio/{id}', [PortfolioController::class, 'show'])->name('portfolio.show');
    Route::get('/portfolio/{id}/export', [PortfolioController::class, 'export'])->name('portfolio.export');
    Route::get('/portfolio/{id}/edit', [PortfolioController::class, 'edit'])->name('portfolio.edit');
    Route::put('/portfolio/{id}', [PortfolioController::class, 'update'])->name('portfolio.update');
    Route::delete('/portfolio/{id}', [PortfolioController::class, 'destroy'])->name('portfolio.destroy');

    // Portfolio stock management routes
    Route::get('/portfolio/{id}/add-stock', [PortfolioController::class, 'addStock'])->name('portfolio.add-stock');
    Route::post('/portfolio/{id}/add-stock', [PortfolioController::class, 'storeStock'])->name('portfolio.store-stock');
    Route::put('/portfolio/item/{itemId}', [PortfolioController::class, 'updateItem'])->name('portfolio.update-item');
    Route::delete('/portfolio/item/{itemId}', [PortfolioController::class, 'removeStock'])->name('portfolio.remove-stock');

    // Portfolio AJAX routes
    Route::post('/portfolio/{id}/update-prices', [PortfolioController::class, 'updatePrices'])->name('portfolio.update-prices');
    Route::get('/portfolio/{id}/rebalance-suggestions', [PortfolioController::class, 'getRebalanceSuggestions'])->name('portfolio.rebalance-suggestions');
});

// Admin routes
Route::prefix('admin')->name('admin.')->group(function () {
    // Admin Authentication (không cần middleware)
    Route::middleware('throttle:5,1')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login'])->name('login.post');
    });
    
    // Admin routes (cần middleware 'admin' để kiểm tra quyền truy cập backend)
    Route::middleware(['auth:web', 'admin'])->group(function () {
        // Dashboard
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Account — self-service, no can: gate (every backend account manages its own)
        Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
        Route::put('/account', [AccountController::class, 'update'])->name('account.update');

        // Timeline - Admin, Webadmin, AdminSupport
        Route::get('/timeline', [TimelineController::class, 'index'])->name('timeline');
        Route::get('/timeline/stats', [TimelineController::class, 'stats'])->name('timeline.stats');
        
        // Users Management — Admin only
        Route::middleware('can:manage-users')->group(function () {
            Route::resource('users', UserController::class);
            Route::post('/users/{user}/verify', [EmailVerificationController::class, 'adminVerify'])->name('users.verify');
            Route::post('/users/{user}/unverify', [EmailVerificationController::class, 'adminUnverify'])->name('users.unverify');
        });

        // Roles & Permissions Management — Admin only (self-service phân quyền,
        // xem docs/RBAC.md)
        Route::middleware('can:manage-roles')->group(function () {
            Route::resource('roles', RoleController::class);
        });
        Route::middleware('can:manage-permissions')->group(function () {
            Route::resource('permissions', PermissionController::class);
        });

        // Stock, News, Portfolio — Admin + AdminSupport only
        Route::middleware('can:manage-features')->group(function () {
            Route::resource('stocks', AdminStockController::class);
            Route::post('/stocks/update-prices', [AdminStockController::class, 'updatePrices'])->name('stocks.update-prices');

            Route::get('/news', [NewsController::class, 'index'])->name('news.index');
            Route::post('/news/update-rss', [NewsController::class, 'updateRss'])->name('news.update-rss');
            Route::resource('news-categories', NewsCategoryController::class)->except(['show']);

            Route::get('/portfolios', [AdminPortfolioController::class, 'index'])->name('portfolios.index');
            Route::get('/portfolios/{portfolio}', [AdminPortfolioController::class, 'show'])->name('portfolios.show');
            Route::patch('/portfolios/{portfolio}/toggle-status', [AdminPortfolioController::class, 'toggleStatus'])->name('portfolios.toggle-status');
            Route::delete('/portfolios/{portfolio}', [AdminPortfolioController::class, 'destroy'])->name('portfolios.destroy');
            Route::get('/portfolios-stats', [AdminPortfolioController::class, 'stats'])->name('portfolios.stats');
        });
        
        // Sync Status — manage-features
        Route::middleware('can:manage-features')->group(function () {
            Route::get('/sync-status', [SyncStatusController::class, 'index'])->name('sync-status');
            Route::post('/sync-status/trigger/{key}', [SyncStatusController::class, 'trigger'])
                ->middleware('throttle:5,1')
                ->name('sync-status.trigger');
        });

        // Queue Monitor — custom Redis queue/failed-jobs dashboard, see docs/RBAC.md
        Route::middleware('can:manage-queue')->group(function () {
            Route::get('/queue', [QueueMonitorController::class, 'index'])->name('queue.index');
            Route::get('/queue/stats', [QueueMonitorController::class, 'stats'])->name('queue.stats');
            Route::post('/queue/failed/retry-all', [QueueMonitorController::class, 'retryAll'])->name('queue.retry-all');
            Route::delete('/queue/failed', [QueueMonitorController::class, 'destroyAll'])->name('queue.destroy-all');
            Route::post('/queue/failed/{uuid}/retry', [QueueMonitorController::class, 'retry'])->name('queue.retry');
            Route::delete('/queue/failed/{uuid}', [QueueMonitorController::class, 'destroy'])->name('queue.destroy');
        });

        // Admin Logout
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
    });
});
