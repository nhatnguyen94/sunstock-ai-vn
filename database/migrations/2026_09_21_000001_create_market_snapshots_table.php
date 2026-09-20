<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('trade_date')->unique();          // the session the numbers belong to (latest index bar)
            $table->longText('data');                      // JSON: indices (+30-session series), exchanges (breadth, value), movers
            $table->longText('quotes')->nullable();        // JSON {symbol: [price, ref, %, volume, value, ceiling, floor]} — newest row only
            $table->timestamp('fetched_at')->nullable();   // when vnstock answered (UTC)
            $table->timestamp('synced_at')->nullable();    // when we stored it
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_snapshots');
    }
};
