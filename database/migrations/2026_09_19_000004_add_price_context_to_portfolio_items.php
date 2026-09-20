<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_items', function (Blueprint $table) {
            // Prior session close (VND) — lets the UI show today's move without another query per holding
            $table->decimal('previous_price', 15, 2)->nullable()->after('current_price');
            // Market date `current_price` refers to (so "updated 11/09" can be shown honestly)
            $table->date('price_date')->nullable()->after('previous_price');
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_items', function (Blueprint $table) {
            $table->dropColumn(['previous_price', 'price_date']);
        });
    }
};
