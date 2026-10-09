<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per AI call (chat or market prediction): who, which model answered, how it ended, how long it took.
        Schema::create('ai_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 10);                       // chat | predict
            $table->string('status', 10);                     // ok | error | refused (kill switch, blocked, daily limit)
            $table->string('model', 80)->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('question', 300)->nullable();      // chat only, cut short
            $table->timestamp('created_at');

            $table->index(['created_at', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('ai_blocked_at')->nullable()->after('status');   // set by an admin, never from request input
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('ai_blocked_at'));
        Schema::dropIfExists('ai_requests');
    }
};
