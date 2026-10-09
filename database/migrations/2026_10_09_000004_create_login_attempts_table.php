<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per sign-in attempt (either door), written by App\Support\LoginAuditor from the framework's Login / Failed events.
        // The typed e-mail is kept because that is what a password guesser changes; the password is never stored. Pruned after 90 days.
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email', 120)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 200)->nullable();
            $table->boolean('success');
            $table->string('door', 5);                 // web | admin
            $table->timestamp('created_at');

            $table->index(['ip', 'created_at']);
            $table->index(['success', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // Addresses an admin turned away by hand (App\Http\Middleware\BlockedIps answers 403 for them).
        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique();
            $table->string('reason', 200)->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_ips');
        Schema::dropIfExists('login_attempts');
    }
};