<?php

namespace App\Support;

use App\Models\SyncRun;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Throwable;

/**
 * Writes one `sync_runs` row for every run of a data-refresh command, whoever started it (the scheduler, the admin's "Sync ngay"
 * button or a person in a terminal): listens to the console events, so no command has to know about it.
 */
class SyncRunRecorder
{
    /** Commands that count as a data refresh. */
    public const PREFIXES = ['sync:', 'signals:'];

    /** @var array<string, float> start time per command name, for the duration */
    private array $started = [];

    public static function tracks(?string $command): bool
    {
        if ($command === null) {
            return false;
        }
        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($command, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function starting(CommandStarting $event): void
    {
        if (self::tracks($event->command)) {
            $this->started[$event->command] = microtime(true);
        }
    }

    public function finished(CommandFinished $event): void
    {
        if (! self::tracks($event->command)) {
            return;
        }

        $began = $this->started[$event->command] ?? microtime(true);
        unset($this->started[$event->command]);

        try {
            SyncRun::create([
                'command' => $event->command,
                'ok' => $event->exitCode === 0,
                'duration_ms' => (int) round((microtime(true) - $began) * 1000),
                'output' => null,
                'ran_at' => now(),
            ]);
        } catch (Throwable) {
            // never let bookkeeping break a sync (for instance before the table exists)
        }
    }
}
