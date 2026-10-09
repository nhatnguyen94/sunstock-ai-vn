<?php

namespace App\Http\Middleware;

use App\Models\BlockedIp;
use App\Support\TransformerResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Turns away addresses an admin blocked (Admin > Bảo mật). The list is cached for a minute and refreshed the moment it changes.
 * Fails open: if the table cannot be read, nobody is blocked rather than everybody.
 */
class BlockedIps
{
    public const CACHE_KEY = 'blocked-ips';

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->ip(), self::all(), true)) {
            return response(TransformerResponse::IP_BLOCKED_MESSAGE, TransformerResponse::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    /** @return string[] */
    public static function all(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 60, fn () => BlockedIp::query()->pluck('ip')->all());
        } catch (Throwable) {
            return [];
        }
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
