<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_symbols', function (Blueprint $table) {
            $table->string('exchange', 20)->nullable()->after('name')->comment('Sàn: HSX, HNX, UPCOM');
            $table->string('industry', 150)->nullable()->after('exchange')->comment('Ngành ICB từ vnstock');
        });
    }

    public function down(): void
    {
        Schema::table('stock_symbols', function (Blueprint $table) {
            $table->dropColumn(['exchange', 'industry']);
        });
    }
};
