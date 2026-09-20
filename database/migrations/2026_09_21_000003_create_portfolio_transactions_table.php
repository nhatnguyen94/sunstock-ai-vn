<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->string('stock_symbol', 12);
            $table->string('stock_name')->nullable();
            $table->string('type', 4);                                   // buy | sell
            $table->unsignedBigInteger('quantity');
            $table->decimal('price', 15, 2);                             // whole VND per share
            $table->decimal('fee', 15, 2)->default(0);                   // commission (+ tax on a sell), VND
            $table->date('traded_at');
            $table->decimal('cost_basis', 15, 2)->nullable();            // sell only: average cost per share when sold
            $table->decimal('realized_pl', 18, 2)->nullable();           // sell only: (price - cost) * qty - fee
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['portfolio_id', 'traded_at']);
            $table->index(['portfolio_id', 'stock_symbol']);
        });

        // Holdings that existed before the ledger get an opening "buy" so history and holdings agree
        $now = now();
        DB::table('portfolio_items')->orderBy('id')->each(function ($item) use ($now) {
            DB::table('portfolio_transactions')->insert([
                'portfolio_id' => $item->portfolio_id,
                'stock_symbol' => $item->stock_symbol,
                'stock_name' => $item->stock_name,
                'type' => 'buy',
                'quantity' => $item->quantity,
                'price' => $item->buy_price,
                'fee' => 0,
                'traded_at' => $item->buy_date ?: substr((string) $item->created_at, 0, 10) ?: $now->toDateString(),
                'notes' => 'Số dư ban đầu',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_transactions');
    }
};
