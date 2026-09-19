<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * "Single flight": when N requests miss the same cache at the same time, only ONE
 * of them runs the expensive producer (a ~4s Python/vnstock subprocess here); the
 * rest wait for the lock and then read the result the first one stored.
 *
 * Without this, a page that is opened by several users right after a cache expiry
 * (or one page firing the same AJAX call twice) spawns duplicate Python processes
 * for identical data — exactly the load PythonRunner's timeouts exist to bound.
 */
class SingleFlight
{
    /**
     * @param string  $key      Identifies the resource (also the lock name)
     * @param Closure $cached   fn(): ?array — returns the stored value, or null on miss
     * @param Closure $produce  fn(): array  — computes AND stores the value (or returns ['error' => ...])
     * @param int     $lockSeconds Must exceed the producer's worst-case runtime
     * @return array
     */
    public static function run(string $key, Closure $cached, Closure $produce, int $lockSeconds = 90): array
    {
        if (($hit = $cached()) !== null) {
            return $hit;
        }

        $lock = Cache::lock('single-flight:' . $key, $lockSeconds);

        try {
            $lock->block($lockSeconds);
        } catch (LockTimeoutException) {
            return ['error' => 'Hệ thống đang bận xử lý yêu cầu này, vui lòng thử lại sau ít giây.', 'busy' => true];
        }

        try {
            // Whoever held the lock before us has probably filled the cache already.
            return $cached() ?? $produce();
        } finally {
            $lock->release();
        }
    }
}
