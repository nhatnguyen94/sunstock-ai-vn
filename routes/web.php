<?php

use App\Backend\Controllers\AdminAuthController;
use App\Backend\Controllers\DashboardController;
use App\Backend\Controllers\NewsController;
use App\Backend\Controllers\PortfolioController as AdminPortfolioController;
use App\Backend\Controllers\StockController as AdminStockController;
use App\Backend\Controllers\TimelineController;
use App\Backend\Controllers\UserController;
use App\Frontend\Controllers\AiController;
use App\Frontend\Controllers\AuthController;
use App\Frontend\Controllers\ExchangeRateController;
use App\Frontend\Controllers\PortfolioController;
use App\Frontend\Controllers\ProfileController;
use App\Frontend\Controllers\StockController;
use Illuminate\Support\Facades\Route;

Route::post('/search', [StockController::class, 'search'])->name('stock.search');

Route::get('/stock', [StockController::class, 'index'])->name('stock.index');

Route::get('/stocks-list', [StockController::class, 'getStockSymbols']);

Route::get('/', [StockController::class, 'home'])->name('home');

Route::get('/exchange-rate', [ExchangeRateController::class, 'index'])->name('exchange-rate.index');
Route::get('/exchange-rate/search', [ExchangeRateController::class, 'search'])->name('exchange-rate.search');

Route::post('/ai-chat', [StockController::class, 'aiChat']);
Route::post('/ai-predict', [AiController::class, 'predict']);

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Profile routes (requires authentication)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Portfolio routes
    Route::get('/portfolio', [PortfolioController::class, 'index'])->name('portfolio.index');
    Route::get('/portfolio/create', [PortfolioController::class, 'create'])->name('portfolio.create');
    Route::post('/portfolio', [PortfolioController::class, 'store'])->name('portfolio.store');
    Route::get('/portfolio/{id}', [PortfolioController::class, 'show'])->name('portfolio.show');
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
    Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.post');
    
    // Admin routes (cần middleware 'admin' để kiểm tra quyền truy cập backend)
    Route::middleware(['auth:web', 'admin'])->group(function () {
        // Dashboard
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        
        // Timeline - Admin, Webadmin, AdminSupport
        Route::get('/timeline', [TimelineController::class, 'index'])->name('timeline');
        Route::get('/timeline/stats', [TimelineController::class, 'stats'])->name('timeline.stats');
        
        // Users Management - Chỉ Admin
        Route::resource('users', UserController::class);
        
        // Stock Management - Admin và AdminSupport
        Route::resource('stocks', AdminStockController::class);
        Route::post('/stocks/update-prices', [AdminStockController::class, 'updatePrices'])->name('stocks.update-prices');
        
        // News Management - Admin và AdminSupport
        Route::get('/news', [NewsController::class, 'index'])->name('news.index');
        Route::post('/news/update-rss', [NewsController::class, 'updateRss'])->name('news.update-rss');
        
        // Portfolio Management - Admin và AdminSupport
        Route::get('/portfolios', [AdminPortfolioController::class, 'index'])->name('portfolios.index');
        Route::get('/portfolios/{portfolio}', [AdminPortfolioController::class, 'show'])->name('portfolios.show');
        Route::patch('/portfolios/{portfolio}/toggle-status', [AdminPortfolioController::class, 'toggleStatus'])->name('portfolios.toggle-status');
        Route::delete('/portfolios/{portfolio}', [AdminPortfolioController::class, 'destroy'])->name('portfolios.destroy');
        Route::get('/portfolios-stats', [AdminPortfolioController::class, 'stats'])->name('portfolios.stats');
        
        // Admin Logout
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
    });
});
