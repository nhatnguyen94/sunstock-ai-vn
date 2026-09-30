<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etfs', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20)->unique();          // FUEVFVND, E1VFVN30 ... (same key as stocks.symbol)
            $table->string('name')->nullable();              // "Quỹ ETF SSIAM VN30"
            $table->string('name_en')->nullable();
            $table->string('exchange', 10)->nullable();      // HOSE
            $table->string('kind', 10)->default('etf');      // etf | closed (listed closed-end fund: Thiên Việt, REIT ...)
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etfs');
    }
};
