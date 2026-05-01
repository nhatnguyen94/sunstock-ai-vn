<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('portfolio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->onDelete('cascade');
            $table->string('stock_symbol');
            $table->string('stock_name');
            $table->integer('quantity');
            $table->decimal('buy_price', 10, 2);
            $table->decimal('current_price', 10, 2);
            $table->date('buy_date');
            $table->decimal('target_price', 10, 2)->nullable();
            $table->decimal('stop_loss_price', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['portfolio_id', 'stock_symbol']);
            $table->index('stock_symbol');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_items');
    }
};
