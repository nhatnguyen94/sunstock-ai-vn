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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Slug dùng trong Gate::before()/can: middleware, vd: manage-users');
            $table->string('display_name')->comment('Tên hiển thị tiếng Việt');
            $table->string('group')->nullable()->comment('Nhóm hiển thị trên UI, vd: Hệ thống, Tính năng');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
