<?php

namespace App\Backend\Services;

use App\Support\DatabaseBackup;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** What Admin > Sức khỏe hệ thống shows: is the scheduler alive, how full is the disk, what runs when, which backups exist. */
class SystemHealthService
{
    public const HEARTBEAT_KEY = 'scheduler:heartbeat';

    public function __construct(private readonly DatabaseBackup $backups) {}

    /**
     * The scheduler writes a heartbeat every minute. No beat at all, or an old one, means nobody is running `schedule:work` / cron.
     *
     * @return array{state: string, last: ?Carbon, minutes: ?int}
     */
    public function scheduler(): array
    {
        $beat = Cache::get(self::HEARTBEAT_KEY);
        $last = is_string($beat) ? Carbon::parse($beat) : null;
        $minutes = $last ? (int) $last->diffInMinutes(now()) : null;

        return [
            'state' => match (true) {
                $last === null => 'off',
                $minutes <= 3 => 'ok',
                $minutes <= 10 => 'warn',
                default => 'bad',
            },
            'last' => $last,
            'minutes' => $minutes,
        ];
    }

    /** @return array{free: int, total: int, free_percent: float}|null null where the disk cannot be read */
    public function disk(): ?array
    {
        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());

        return $free === false || $total === false || $total <= 0
            ? null
            : ['free' => (int) $free, 'total' => (int) $total, 'free_percent' => round($free / $total * 100, 1)];
    }

    /** @return array<int, array{expression: string, task: string, next: ?Carbon}> every scheduled task, soonest first */
    public function schedule(): array
    {
        // `withSchedule()` in bootstrap/app.php runs when the console application starts, which a web request never does: without this the
        // Schedule is empty here (the page showed "0 tác vụ"). all() boots the console app, which registers every task.
        Artisan::all();

        $rows = collect(app(Schedule::class)->events())->map(function (Event $event) {
            try {
                $next = Carbon::instance($event->nextRunDate());
            } catch (Throwable) {
                $next = null;
            }

            return ['expression' => $event->expression, 'task' => $this->taskName($event), 'next' => $next];
        });

        return $rows->sortBy(fn ($r) => $r['next']?->timestamp ?? PHP_INT_MAX)->values()->all();
    }

    private function taskName(Event $event): string
    {
        if (is_string($event->command) && preg_match("/artisan'?\s+(.+)$/", $event->command, $m)) {
            return trim($m[1], "' ");
        }

        return $event->description ?: 'tác vụ nội bộ';
    }

    /**
     * Backups found on disk, newest first. The id is a hash of the file path, so a download link never carries a path.
     *
     * @return array<int, array{id: string, name: string, modified: int, size: int}>
     */
    public function backups(int $limit = 10): array
    {
        return collect($this->backups->all())->take($limit)->map(fn (array $b) => [
            'id' => $this->backupId($b['path']),
            'name' => basename(dirname($b['path'], 3)).'/'.basename(dirname($b['path'], 2)).'/'.basename(dirname($b['path'])).'/'.basename($b['path']),
            'modified' => $b['modified'],
            'size' => $b['size'],
        ])->all();
    }

    /** The file behind a backup id, or null: only files the backup scan itself found can ever be served. */
    public function backupPath(string $id): ?string
    {
        foreach ($this->backups->all() as $b) {
            if (hash_equals($this->backupId($b['path']), $id)) {
                return $b['path'];
            }
        }

        return null;
    }

    /** Age in days of the newest valid backup, or null when there is none. */
    public function backupAgeDays(): ?int
    {
        $latest = $this->backups->latest();

        return $latest ? (int) floor((time() - $latest['modified']) / 86400) : null;
    }

    private function backupId(string $path): string
    {
        return substr(hash('sha256', $path), 0, 24);
    }

    /** @return array<string, mixed> */
    public function environment(): array
    {
        return [
            'php' => PHP_VERSION,
            'laravel' => Application::VERSION,
            'env' => (string) config('app.env'),
            'debug' => (bool) config('app.debug'),
            'cache' => (string) config('cache.default'),
            'queue' => (string) config('queue.default'),
            'session' => (string) config('session.driver'),
        ];
    }
}
