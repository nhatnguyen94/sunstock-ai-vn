<?php

namespace Tests\Feature\Console\Commands;

use App\Models\QueueJobLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class PruneQueueJobLogsTest extends TestCase
{
    use RefreshDatabase;

    #[Group('queueMonitor')]
    public function test_marks_long_stuck_processing_rows_as_stale(): void
    {
        $stuck = QueueJobLog::create([
            'job_id' => 'stuck-1', 'job_class' => 'X', 'queue' => 'default',
            'status' => 'processing', 'started_at' => now()->subMinutes(30),
        ]);
        $fresh = QueueJobLog::create([
            'job_id' => 'fresh-1', 'job_class' => 'X', 'queue' => 'default',
            'status' => 'processing', 'started_at' => now()->subMinutes(2),
        ]);

        $this->artisan('queue-logs:prune')->assertSuccessful();

        $this->assertSame('stale', $stuck->fresh()->status);
        $this->assertSame('processing', $fresh->fresh()->status);
    }

    #[Group('queueMonitor')]
    public function test_deletes_old_finished_rows_but_keeps_recent_ones(): void
    {
        $old = QueueJobLog::create([
            'job_id' => 'old-1', 'job_class' => 'X', 'queue' => 'default',
            'status' => 'completed', 'started_at' => now()->subDays(10), 'finished_at' => now()->subDays(10),
        ]);
        $old->forceFill(['updated_at' => now()->subDays(10)])->saveQuietly();

        $recent = QueueJobLog::create([
            'job_id' => 'recent-1', 'job_class' => 'X', 'queue' => 'default',
            'status' => 'completed', 'started_at' => now()->subHour(), 'finished_at' => now()->subHour(),
        ]);

        $this->artisan('queue-logs:prune')->assertSuccessful();

        $this->assertDatabaseMissing('queue_job_logs', ['job_id' => 'old-1']);
        $this->assertDatabaseHas('queue_job_logs', ['job_id' => 'recent-1']);
    }
}
