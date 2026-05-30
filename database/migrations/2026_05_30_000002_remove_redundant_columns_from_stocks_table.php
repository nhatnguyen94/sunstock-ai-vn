<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn(['exchange', 'industry', 'market_cap']);
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->string('exchange')->nullable()->after('name');
            $table->string('industry')->nullable()->after('exchange');
            $table->decimal('market_cap', 15, 2)->nullable()->after('industry');
        });
    }
};
