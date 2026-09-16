<?php

namespace App\Backend\Repositories;

use App\Backend\Interfaces\QueueMonitorRepositoryInterface;
use App\Models\QueueJobLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Collection;

class QueueMonitorRepository implements QueueMonitorRepositoryInterface
{
    public function queueSizes(array $queues): Collection
    {
        /** @var \Illuminate\Queue\RedisQueue $connection */
        $connection = Queue::connection('redis');

        return collect($queues)->map(fn (string $queue) => [
            'name'     => $queue,
            'pending'  => $connection->pendingSize($queue),
            'delayed'  => $connection->delayedSize($queue),
            'reserved' => $connection->reservedSize($queue),
        ]);
    }

    public function failedJobs(int $perPage = 20): LengthAwarePaginator
    {
        return DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->paginate($perPage);
    }

    public function findFailedJob(string $uuid): ?object
    {
        return DB::table('failed_jobs')->where('uuid', $uuid)->first();
    }

    public function retryFailedJob(string $uuid): void
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);
    }

    public function deleteFailedJob(string $uuid): void
    {
        Artisan::call('queue:forget', ['id' => $uuid]);
    }

    public function retryAllFailedJobs(): int
    {
        $count = DB::table('failed_jobs')->count();

        Artisan::call('queue:retry', ['id' => ['all']]);

        return $count;
    }

    public function currentlyProcessing(): Collection
    {
        return QueueJobLog::where('status', 'processing')
            ->orderByDesc('started_at')
            ->get();
    }

    public function recentlyFinished(int $limit = 20): Collection
    {
        return QueueJobLog::whereIn('status', ['completed', 'failed'])
            ->orderByDesc('finished_at')
            ->limit($limit)
            ->get();
    }

    public function processedTodayCount(): int
    {
        return QueueJobLog::where('status', 'completed')
            ->whereDate('finished_at', today())
            ->count();
    }
}
