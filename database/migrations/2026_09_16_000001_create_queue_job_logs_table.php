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
        Schema::create('queue_job_logs', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->comment('Illuminate\Queue\Job::getJobId() — stable across a job\'s own retries, changes on a fresh queue:retry push');
            $table->string('job_class');
            $table->string('queue');
            $table->string('summary')->nullable()->comment('Human-readable identifier, e.g. stock symbol — see Job::queueSummary()');
            $table->enum('status', ['processing', 'completed', 'failed', 'stale'])->default('processing');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index('job_id');
            $table->index(['status', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_job_logs');
    }
};
