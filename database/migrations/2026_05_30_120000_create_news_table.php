<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->string('url', 1000);
            $table->char('url_hash', 32)->unique(); // MD5(url) for fast dedup
            $table->string('source', 100);          // e.g. VnExpress, CafeF
            $table->string('image_url', 1000)->nullable();
            $table->string('category', 100)->nullable();
            $table->dateTime('published_at');
            $table->dateTime('synced_at');
            $table->timestamps();

            $table->index('source');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news');
    }
};
