<?php

namespace App\Support;

use App\Models\QueueJobLog;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;

/**
 * Records a lightweight processing log (started/finished/duration/summary)
 * for every queued job, powering the "currently processing" / "recently
 * completed" sections of Admin > Giám sát Queue. Never lets logging failures
 * affect the actual job — same defensive pattern as App\Support\ActivityLogger.
 */
class QueueJobLogger
{
    public static function processing(JobProcessing $event): void
    {
        try {
            $payload = $event->job->payload();

            QueueJobLog::create([
                'job_id'      => $event->job->getJobId(),
                'job_class'   => $event->job->resolveName(),
                'queue'       => $event->job->getQueue() ?? 'default',
                'summary'     => self::extractSummary($payload),
                'status'      => 'processing',
                'started_at'  => now(),
            ]);
        } catch (\Throwable) {
            // Never let logging crash the worker
        }
    }

    public static function processed(JobProcessed $event): void
    {
        self::finish($event->job, 'completed');
    }

    public static function failed(JobFailed $event): void
    {
        self::finish($event->job, 'failed');
    }

    private static function finish(JobContract $job, string $status): void
    {
        try {
            $log = QueueJobLog::where('job_id', $job->getJobId())
                ->where('status', 'processing')
                ->latest('id')
                ->first();

            if (!$log) {
                return;
            }

            $finishedAt = now();

            $log->update([
                'status'      => $status,
                'finished_at' => $finishedAt,
                'duration_ms' => $log->started_at->diffInMilliseconds($finishedAt),
            ]);
        } catch (\Throwable) {
            // Never let logging crash the worker
        }
    }

    /**
     * Best-effort human-readable identifier (e.g. a stock symbol) by
     * unserializing the job command and calling its queueSummary() method,
     * if it has one. Deliberately conservative — falls back to null (just
     * the job class name shows in the UI) on anything unexpected, since this
     * is cosmetic and must never risk crashing a real queue worker.
     */
    private static function extractSummary(array $payload): ?string
    {
        $commandStr = $payload['data']['command'] ?? null;

        if (!is_string($commandStr) || !str_starts_with($commandStr, 'O:')) {
            return null;
        }

        try {
            $command = unserialize($commandStr);
        } catch (\Throwable) {
            return null;
        }

        if (!is_object($command) || !method_exists($command, 'queueSummary')) {
            return null;
        }

        try {
            $summary = $command->queueSummary();

            return is_string($summary) ? mb_substr($summary, 0, 255) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
