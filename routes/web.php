<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StockController;
use App\Http\Controllers\ExchangeRateController;

Route::post('/search', [StockController::class, 'search'])->name('stock.search');

Route::get('/stock', [StockController::class, 'index'])->name('stock.index');

Route::get('/stocks-list', [StockController::class, 'getStockSymbols']);

Route::get('/', [StockController::class, 'home']);

Route::get('/exchange-rate', [ExchangeRateController::class, 'index'])->name('exchange-rate.index');

Route::post('/ai-chat', [StockController::class, 'aiChat']);