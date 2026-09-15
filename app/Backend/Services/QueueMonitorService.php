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
}
