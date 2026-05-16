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
        Schema::table('stocks', function (Blueprint $table) {
            $table->string('exchange')->nullable()->after('name')->comment('Sàn giao dịch: HSX, HNX, UPCOM');
            $table->string('industry')->nullable()->after('exchange')->comment('Ngành nghề');
            $table->decimal('market_cap', 15, 2)->nullable()->after('industry')->comment('Vốn hóa thị trường');
            $table->boolean('is_active')->default(true)->after('market_cap')->comment('Trạng thái hoạt động');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn(['exchange', 'industry', 'market_cap', 'is_active']);
        });
    }
};
