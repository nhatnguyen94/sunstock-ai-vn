<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly OHLCV aggregates per stock.
 *
 * Purpose:
 *  - Provides fast chart data for multi-year views (3Y, 5Y, All)
 *  - 400 stocks × 12 months/year = ~4,800 rows/year vs ~100k daily rows
 *  - Populated/refreshed by: php artisan generate:price-summaries
 *  - Used by StockRepository when date range > 1 year
 *
 * Columns:
 *  - period_start  : first calendar day of the month (e.g., 2026-05-01)
 *  - open          : open price of the first trading day of the month
 *  - high          : MAX(high) across all trading days in the month
 *  - low           : MIN(low) across all trading days in the month
 *  - close         : close price of the last trading day of the month
 *  - volume        : SUM(volume) across all trading days in the month
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_price_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_id');
            $table->date('period_start'); // First day of month: 2026-05-01
            $table->decimal('open', 12, 2);
            $table->decimal('high', 12, 2);
            $table->decimal('low', 12, 2);
            $table->decimal('close', 12, 2);
            $table->bigInteger('volume');

            $table->unique(['stock_id', 'period_start']);
            $table->index('period_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_price_summaries');
    }
};
