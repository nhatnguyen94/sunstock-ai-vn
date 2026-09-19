<?php

namespace App\Backend\Services;

use App\Backend\Interfaces\QueueMonitorRepositoryInterface;
use App\Backend\Interfaces\QueueMonitorServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class QueueMonitorService implements QueueMonitorServiceInterface
{
    /** Queues actually used by this app — see docker/php/supervisord.conf's --queue=high,default. */
    private const QUEUES = ['high', 'default'];

    public function __construct(
        protected QueueMonitorRepositoryInterface $queueMonitorRepository
    ) {}

    public function getQueueStats(): Collection
    {
        return $this->queueMonitorRepository->queueSizes(self::QUEUES);
    }

    public function getFailedJobs(int $perPage = 20): LengthAwarePaginator
    {
        $paginator = $this->queueMonitorRepository->failedJobs($perPage);

        $paginator->setCollection(
            $paginator->getCollection()->map(function ($job) {
                $payload = json_decode($job->payload, true);
                $job->job_class = $payload['displayName'] ?? 'Unknown';
                $job->short_exception = str($job->exception)->before("\n")->limit(200)->toString();

                return $job;
            })
        );

        return $paginator;
    }

    public function retryFailedJob(string $uuid): bool
    {
        if (!$this->queueMonitorRepository->findFailedJob($uuid)) {
            return false;
        }

        $this->queueMonitorRepository->retryFailedJob($uuid);

        return true;
    }

    public function deleteFailedJob(string $uuid): bool
    {
        if (!$this->queueMonitorRepository->findFailedJob($uuid)) {
            return false;
        }

        $this->queueMonitorRepository->deleteFailedJob($uuid);

        return true;
    }

    public function retryAllFailedJobs(): int
    {
        return $this->queueMonitorRepository->retryAllFailedJobs();
    }

    public function deleteAllFailedJobs(): int
    {
        return $this->queueMonitorRepository->deleteAllFailedJobs();
    }

    public function getLiveActivity(): array
    {
        $now = now();

        $processing = $this->queueMonitorRepository->currentlyProcessing()->map(fn ($log) => [
            'job_class'      => class_basename($log->job_class),
            'summary'        => $log->summary,
            'queue'          => $log->queue,
            'started_at'     => $log->started_at->format('H:i:s d/m'),
            'elapsed_seconds' => (int) $log->started_at->diffInSeconds($now),
        ]);

        $recent = $this->queueMonitorRepository->recentlyFinished()->map(fn ($log) => [
            'job_class'    => class_basename($log->job_class),
            'summary'      => $log->summary,
            'queue'        => $log->queue,
            'status'       => $log->status,
            'finished_at'  => $log->finished_at?->format('H:i:s d/m'),
            'duration_display' => $log->duration_ms === null ? '—' : $this->formatDuration($log->duration_ms),
        ]);

        return [
            'processing' => $processing,
            'recent' => $recent,
            'processedToday' => $this->queueMonitorRepository->processedTodayCount(),
        ];
    }

    private function formatDuration(int $ms): string
    {
        if ($ms < 1000) {
            return "{$ms}ms";
        }

        $seconds = round($ms / 1000, 1);

        return "{$seconds}s";
    }
}
