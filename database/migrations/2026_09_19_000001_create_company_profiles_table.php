<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20)->unique();
            // Whole normalized profile from py/get_company_profile.py:
            // overview / ownership / shareholders / officers / subsidiaries / affiliates / events
            $table->json('data');
            $table->timestamp('synced_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};
