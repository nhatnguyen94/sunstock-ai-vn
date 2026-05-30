<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('url_hash');
        });

        // Populate category_id from existing category string
        $map = DB::table('news_categories')->pluck('id', 'name')->toArray();

        foreach ($map as $name => $id) {
            DB::table('news')->where('category', $name)->update(['category_id' => $id]);
        }

        // Rows with unknown category → default to 'Kinh doanh' (id=1)
        DB::table('news')->whereNull('category_id')->update(['category_id' => $map['Kinh doanh'] ?? 1]);

        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn('category');
            $table->foreign('category_id')->references('id')->on('news_categories')->onDelete('set null');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['category_id']);
            $table->dropColumn('category_id');
            $table->string('category', 100)->nullable();
        });
    }
};
