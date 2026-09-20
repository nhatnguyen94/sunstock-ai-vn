<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gold_prices', function (Blueprint $table) {
            $table->id();
            $table->string('source', 8);                   // SJC | BTMC
            $table->string('metal', 8)->default('gold');   // gold | silver
            $table->string('product', 191);
            $table->string('branch', 60)->default('');     // SJC quotes per branch; '' for BTMC
            $table->string('purity', 12)->nullable();      // 999.9 ...
            $table->string('unit', 8)->default('luong');   // gold: VND per luong (37.5 g); silver: VND per pack named in `product`
            $table->unsignedBigInteger('buy_price');       // VND — what the shop pays you
            $table->unsignedBigInteger('sell_price')->nullable();   // VND — what you pay (NULL when not quoted)
            $table->decimal('world_price', 10, 2)->nullable();      // USD per troy ounce, when the source publishes it
            $table->timestamp('quoted_at');                // UTC. BTMC: its own timestamp; SJC: when we first saw this price
            $table->timestamp('synced_at')->nullable();

            $table->unique(['source', 'product', 'branch', 'quoted_at'], 'gold_prices_unique_quote');
            $table->index(['source', 'metal', 'quoted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gold_prices');
    }
};
