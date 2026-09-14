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
        Schema::table('portfolio_items', function (Blueprint $table) {
            $table->timestamp('target_alerted_at')->nullable()->after('target_price');
            $table->timestamp('stop_loss_alerted_at')->nullable()->after('stop_loss_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('portfolio_items', function (Blueprint $table) {
            $table->dropColumn(['target_alerted_at', 'stop_loss_alerted_at']);
        });
    }
};
