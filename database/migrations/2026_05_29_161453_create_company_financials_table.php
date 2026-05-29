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
        Schema::create('company_financials', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20)->index();
            // report type: income | balance | cashflow | ratio
            $table->enum('type', ['income', 'balance', 'cashflow', 'ratio']);
            // period granularity: quarter | year
            $table->enum('period', ['quarter', 'year']);
            // Full JSON response: { "data": [...rows...], "periods": [...] }
            $table->json('raw_data');
            // When this record was last fetched from vnstock
            $table->timestamp('synced_at')->nullable();

            // One record per symbol+type+period combination
            $table->unique(['symbol', 'type', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_financials');    }
};
