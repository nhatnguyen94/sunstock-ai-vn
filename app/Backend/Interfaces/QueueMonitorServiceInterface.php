<?php

namespace App\Backend\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface QueueMonitorServiceInterface
{
    /** @return Collection<int, array{name: string, pending: int, delayed: int, reserved: int}> */
    public function getQueueStats(): Collection;

    /** @return LengthAwarePaginator paginated failed jobs, each with job_class/short_exception added */
    public function getFailedJobs(int $perPage = 20): LengthAwarePaginator;

    public function retryFailedJob(string $uuid): bool;

    public function deleteFailedJob(string $uuid): bool;

    public function retryAllFailedJobs(): int;

    /** @return array{processing: Collection, recent: Collection, processedToday: int} */
    public function getLiveActivity(): array;
}
