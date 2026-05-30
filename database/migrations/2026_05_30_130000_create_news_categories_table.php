<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 120)->unique();
            $table->timestamps();
        });

        // Seed initial categories from current data
        $categories = [
            ['name' => 'Kinh doanh',  'slug' => 'kinh-doanh'],
            ['name' => 'Chứng khoán', 'slug' => 'chung-khoan'],
            ['name' => 'Thị trường',  'slug' => 'thi-truong'],
            ['name' => 'Doanh nghiệp','slug' => 'doanh-nghiep'],
        ];

        $now = now()->toDateTimeString();
        foreach ($categories as &$cat) {
            $cat['created_at'] = $now;
            $cat['updated_at'] = $now;
        }

        DB::table('news_categories')->insert($categories);
    }

    public function down(): void
    {
        Schema::dropIfExists('news_categories');
    }
};
