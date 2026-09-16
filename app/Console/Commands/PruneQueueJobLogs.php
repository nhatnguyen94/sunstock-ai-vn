<?php

namespace App\Console\Commands;

use App\Models\QueueJobLog;
use Illuminate\Console\Command;

/**
 * Keeps queue_job_logs from growing forever, and un-stucks the "currently
 * processing" view if a worker crashed mid-job (killed, OOM, container
 * restart) without ever firing JobProcessed/JobFailed — such a row would
 * otherwise show as "processing" in Admin > Giám sát Queue forever.
 *
 * Run hourly via the scheduler (see bootstrap/app.php).
 */
class PruneQueueJobLogs extends Command
{
    protected $signature = 'queue-logs:prune';

    protected $description = 'Mark orphaned "processing" queue job logs as stale, and delete old finished ones';

    /** Longer than any job's own --timeout (max 600s) plus a safety margin. */
    private const STALE_AFTER_MINUTES = 20;

    private const KEEP_FINISHED_FOR_DAYS = 3;

    public function handle(): int
    {
        $staleCount = QueueJobLog::where('status', 'processing')
            ->where('started_at', '<', now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->update(['status' => 'stale']);

        $deletedCount = QueueJobLog::whereIn('status', ['completed', 'failed', 'stale'])
            ->where('updated_at', '<', now()->subDays(self::KEEP_FINISHED_FOR_DAYS))
            ->delete();

        $this->info("Marked {$staleCount} stuck job(s) as stale, deleted {$deletedCount} old log(s).");

        return 0;
    }
}
