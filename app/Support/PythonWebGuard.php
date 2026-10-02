<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Protects the server from Python processes started by visitors (see config/python_limits.php).
 *
 * Every anonymous page that can miss its cache used to be able to start a subprocess of up to a minute for as many
 * requests as the per-IP route throttle allowed, so one machine could keep dozens of them running and burn the shared
 * vnstock quota. Two limits now apply to a run started from a web request:
 *   1. at most N processes at once in total (Redis locks used as a counting semaphore);
 *   2. a per-visitor budget per minute and per hour, counted only for processes that really start.
 *
 * Queue workers, the scheduler and artisan commands never pass through here.
 */
final class PythonWebGuard
{
    /** Exit code reported for a refused run (EX_TEMPFAIL): "try again later", not a script error. */
    public const EXIT_REFUSED = 75;

    /** Is this call made on behalf of a web request (and therefore limited)? */
    public static function applies(): bool
    {
        return ! app()->runningInConsole() || (bool) config('python_limits.web.enforce_in_console');
    }

    /**
     * Ask permission to start one process that may run for up to $timeoutSeconds.
     *
     * @return Closure|null a function that releases the slot (call it when the process ends), or null = refused
     */
    public static function enter(int $timeoutSeconds): ?Closure
    {
        [$who, $perMinute, $perHour] = self::visitor();

        if (RateLimiter::tooManyAttempts("python-web:min:{$who}", $perMinute)
            || RateLimiter::tooManyAttempts("python-web:hour:{$who}", $perHour)) {
            Log::notice('PythonWebGuard: visitor budget used up', ['visitor' => $who]);

            return null;
        }

        $lock = self::takeSlot($timeoutSeconds);
        if ($lock === null) {
            Log::notice('PythonWebGuard: every web slot is busy', ['visitor' => $who]);

            return null;
        }

        // Counted only now, when a process really starts: refused calls and cache hits cost the visitor nothing
        RateLimiter::hit("python-web:min:{$who}", 60);
        RateLimiter::hit("python-web:hour:{$who}", 3600);

        return fn () => $lock->release();
    }

    /** @return array{0: string, 1: int, 2: int} visitor key, per-minute budget, per-hour budget */
    private static function visitor(): array
    {
        $request = request();
        $userId = Auth::id();

        return $userId !== null
            ? ["user:{$userId}", (int) config('python_limits.web.user.per_minute'), (int) config('python_limits.web.user.per_hour')]
            : ['ip:'.$request->ip(), (int) config('python_limits.web.guest.per_minute'), (int) config('python_limits.web.guest.per_hour')];
    }

    private static function takeSlot(int $timeoutSeconds): ?Lock
    {
        // The lock outlives the longest possible run by a margin, so a worker that dies cannot hold a slot forever
        $ttl = $timeoutSeconds + 15;

        for ($slot = 1, $max = (int) config('python_limits.web.max_concurrent'); $slot <= $max; $slot++) {
            $lock = Cache::lock("python-web-slot:{$slot}", $ttl);
            if ($lock->get()) {
                return $lock;
            }
        }

        return null;
    }
}
