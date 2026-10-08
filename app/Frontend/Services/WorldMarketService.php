<?php

namespace App\Frontend\Services;

use App\Jobs\SyncWorldMarketsJob;
use App\Support\PythonRunner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The world indices strip on the home page (S&P 500, Nasdaq, Dow, FTSE, DAX, Nikkei, Hang Seng, Shanghai) from `py/get_world_markets.py`
 * (vnstock, MSN source). The latest answer is kept in the cache (it can always be fetched again, so no table); pages only READ it: a missing
 * or old answer queues one deduplicated background refresh and the strip simply stays out of the way until it arrives. No page ever waits for
 * the subprocess.
 */
class WorldMarketService
{
    public const CACHE_KEY = 'world:markets:v1';

    /** Cached answers older than this are refreshed in the background (the strip keeps showing the old one meanwhile). */
    public const STALE_MINUTES = 90;

    private const PYTHON_TIMEOUT = 60;

    public function __construct(private readonly MarketOverviewService $market) {}

    /**
     * @return array{markets: array<int, array<string, mixed>>, synced_at: Carbon}|null null while nothing has been fetched yet
     */
    public function snapshot(): ?array
    {
        $data = Cache::get(self::CACHE_KEY);
        $syncedAt = isset($data['synced_at']) ? Carbon::parse($data['synced_at']) : null;

        if ($data === null || $syncedAt === null || $syncedAt->diffInMinutes(now()) >= self::STALE_MINUTES) {
            $this->queueRefresh();
        }
        if ($data === null || empty($data['markets'])) {
            return null;
        }

        return [
            'markets' => array_map(fn (array $m) => $m + ['spark' => $this->market->sparkPoints($m['series'] ?? [])], $data['markets']),
            'synced_at' => $syncedAt ?? now(),
        ];
    }

    /** @return array{markets: int, errors: array<string, string>}|array{error: string} */
    public function sync(): array
    {
        $data = $this->runScript();

        if ($data === null) {
            Log::warning('WorldMarketService: no JSON from get_world_markets.py');

            return ['error' => 'Không lấy được dữ liệu thị trường thế giới.'];
        }
        if (isset($data['error'])) {
            return ['error' => (string) $data['error']];
        }
        if (empty($data['markets'])) {
            return ['error' => 'Không có chỉ số thế giới nào trả lời.'];
        }

        Cache::put(self::CACHE_KEY, array_merge($data, ['synced_at' => now()->toIso8601String()]), now()->addDay());

        return ['markets' => count($data['markets']), 'errors' => $data['errors'] ?? []];
    }

    /** @return bool true when a job was queued (false = one is already pending) */
    public function queueRefresh(): bool
    {
        if (! Cache::add('world:markets:queued', 1, 300)) {
            return false;
        }

        SyncWorldMarketsJob::dispatch();

        return true;
    }

    /** Protected so tests can stub the subprocess. */
    protected function runScript(): ?array
    {
        return PythonRunner::runAndDecodeJson(base_path('py/get_world_markets.py'), [], self::PYTHON_TIMEOUT);
    }
}
