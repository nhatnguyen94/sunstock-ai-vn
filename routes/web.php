<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StockController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AiController;

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