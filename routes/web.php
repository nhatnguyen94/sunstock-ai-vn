<?php

use Illuminate\Support\Facades\Route;
use App\Frontend\Controllers\StockController;
use App\Frontend\Controllers\ExchangeRateController;
use App\Frontend\Controllers\AuthController;
use App\Frontend\Controllers\AiController;
use App\Backend\Controllers\DashboardController;

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

Route::prefix('admin')->group(function () {
    Route::get('/login', [\App\Backend\Controllers\AdminAuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/login', [\App\Backend\Controllers\AdminAuthController::class, 'login'])->name('admin.login.post');
    Route::post('/logout', [\App\Backend\Controllers\AdminAuthController::class, 'logout'])->name('admin.logout');

    Route::middleware(['auth:web', 'permission:access_backend'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
        // Các route backend khác
    });
});