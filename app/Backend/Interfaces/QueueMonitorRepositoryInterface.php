<?php

namespace App\Backend\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface QueueMonitorRepositoryInterface
{
    /**
     * Pending/delayed/reserved counts per queue name.
     *
     * @param string[] $queues
     * @return Collection<int, array{name: string, pending: int, delayed: int, reserved: int}>
     */
    public function queueSizes(array $queues): Collection;

    public function failedJobs(int $perPage = 20): LengthAwarePaginator;

    public function findFailedJob(string $uuid): ?object;

    public function retryFailedJob(string $uuid): void;

    public function deleteFailedJob(string $uuid): void;

    public function retryAllFailedJobs(): int;

    /** Deletes every row in failed_jobs, returns how many were removed. */
    public function deleteAllFailedJobs(): int;

    /** Jobs currently being worked on right now (status=processing), most recent first. */
    public function currentlyProcessing(): Collection;

    /** Jobs that finished (completed or failed) most recently, most recent first. */
    public function recentlyFinished(int $limit = 20): Collection;

    public function processedTodayCount(): int;
}
