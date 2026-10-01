<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Account lifecycle, separate from "has the e-mail address been confirmed":
 *   0 inactive (switched off by an admin), 1 active, 2 pending (waiting for e-mail verification), 4 blocked.
 * Only an active account can sign in and keep a session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('status')->default(2)->after('remember_token')->index();
        });

        // Existing accounts keep working: confirmed e-mail => active, unconfirmed => pending
        DB::table('users')->whereNotNull('email_verified_at')->update(['status' => 1]);
        DB::table('users')->whereNull('email_verified_at')->update(['status' => 2]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
