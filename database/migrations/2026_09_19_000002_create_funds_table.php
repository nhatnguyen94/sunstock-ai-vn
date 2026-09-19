<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funds', function (Blueprint $table) {
            $table->id();
            $table->string('short_name', 40)->unique();           // e.g. DCDS, VCBF-BCF
            $table->string('name');
            $table->string('fund_type', 40)->nullable();           // Fmarket label: "Quỹ cổ phiếu"...
            $table->string('type_code', 12)->nullable()->index();  // STOCK | BOND | BALANCED | MMF | OTHER
            $table->string('fund_owner_name')->nullable();
            $table->decimal('management_fee', 6, 2)->nullable();   // % / year
            $table->date('inception_date')->nullable();
            $table->decimal('nav', 14, 2)->nullable();             // VND per unit
            $table->date('nav_update_at')->nullable();

            // Returns in %, straight from Fmarket. NULL = fund too young for that window.
            $table->decimal('nav_change_previous', 8, 2)->nullable();
            $table->decimal('nav_change_1m', 8, 2)->nullable();
            $table->decimal('nav_change_3m', 8, 2)->nullable();
            $table->decimal('nav_change_6m', 8, 2)->nullable();
            $table->decimal('nav_change_12m', 8, 2)->nullable();
            $table->decimal('nav_change_24m', 8, 2)->nullable();
            $table->decimal('nav_change_36m', 8, 2)->nullable();
            $table->decimal('nav_change_36m_annualized', 8, 2)->nullable();
            $table->decimal('nav_change_last_year', 8, 2)->nullable();
            $table->decimal('nav_change_inception', 10, 2)->nullable();

            $table->unsignedInteger('fund_id_fmarket')->nullable();
            $table->timestamp('synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funds');
    }
};
