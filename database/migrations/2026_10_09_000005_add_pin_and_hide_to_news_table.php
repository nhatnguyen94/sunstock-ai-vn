<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin > News: an article can be hidden from the public pages or pinned to the top of the home page. The RSS sync only inserts
        // new rows, so these flags survive every sync.
        Schema::table('news', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('image_url');
            $table->timestamp('pinned_at')->nullable()->after('is_hidden');
        });
    }

    public function down(): void
    {
        Schema::table('news', fn (Blueprint $table) => $table->dropColumn(['is_hidden', 'pinned_at']));
    }
};