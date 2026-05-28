<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hot_industries', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20)->index();
            $table->string('organ_name')->nullable();
            $table->string('icb_name3', 100)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hot_industries');
    }
};
