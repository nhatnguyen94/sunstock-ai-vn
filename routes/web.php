<?php

use App\Backend\Controllers\AdminAuthController;
use App\Backend\Controllers\DashboardController;
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

Route::get('/', [StockController::class, 'home']);

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

Route::prefix('admin')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('admin.login.post');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

    Route::middleware(['auth:web', 'permission:access_backend'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
        // Các route backend khác
    });
});
