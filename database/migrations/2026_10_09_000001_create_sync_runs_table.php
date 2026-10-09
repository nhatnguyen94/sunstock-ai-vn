<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per run of a sync:* / signals:* command (scheduled or manual), written by App\Support\SyncRunRecorder.
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('command', 60);
            $table->boolean('ok')->default(true);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('output', 500)->nullable();
            $table->timestamp('ran_at');

            $table->index(['command', 'ran_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
    }
};
