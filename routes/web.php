<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StockController;

Route::post('/search', [StockController::class, 'search'])->name('stock.search');

Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
