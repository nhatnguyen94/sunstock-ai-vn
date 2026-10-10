<?php

namespace App\Frontend\Services;

use App\Frontend\Interfaces\ExchangeRateRepositoryInterface;
use App\Frontend\Interfaces\GoldPriceRepositoryInterface;
use App\Jobs\SyncGoldPricesJob;
use App\Models\GoldPrice;
use App\Support\PythonRunner;
use App\Support\SingleFlight;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GoldPriceService
{
    /** Two fast HTTP calls in parallel inside one script (~4s incl. Python start-up). */
    private const PYTHON_TIMEOUT = 60;

    /** A page view older than this queues one background refresh (stale-while-revalidate). */
    public const STALE_MINUTES = 20;

    private const MANUAL_COOLDOWN = 120;

    private const VN_TZ = 'Asia/Ho_Chi_Minh';

    /** Chart windows: key => hours back (null = everything we have). */
    public const RANGES = ['1D' => 24, '7D' => 168, '30D' => 720, 'ALL' => null];

    /** Outcome of the last sync (when it ran, which source failed): rows are only written for NEW prices, so the table alone cannot tell a quiet source from a dead one. */
    public const STATUS_CACHE_KEY = 'gold:source-status';

    /** BTMC publishes several times a day on weekdays; a weekend plus a Monday morning stays under this, three days without a publication does not. */
    public const BTMC_MAX_AGE_HOURS = 72;

    /** The scheduler runs every 15 minutes, 07:00–19:00 Vietnam time, every day: no recorded run for a day means it is not running (PC off, Docker stopped). */
    public const SYNC_MAX_AGE_HOURS = 24;

    public function __construct(
        private readonly GoldPriceRepositoryInterface $repo,
        private readonly ExchangeRateRepositoryInterface $rates
    ) {}

    // ── Page data ───────────────────────────────────────────────────────────

    /**
     * Everything the page shows. The first ever visit (empty table) loads live once; afterwards it is DB-only
     * and a stale table only queues a background refresh.
     *
     * @return array<string, mixed>
     */
    public function page(): array
    {
        $error = null;
        if ($this->repo->count() === 0) {
            $result = SingleFlight::run(
                'gold-sync',
                fn () => $this->repo->count() > 0 ? ['count' => $this->repo->count()] : null,
                fn () => $this->sync(),
                self::PYTHON_TIMEOUT + 30
            );
            $error = $result['error'] ?? null;
        }

        $syncedAt = $this->repo->lastSyncedAt();
        $stale = $syncedAt === null || $syncedAt->diffInMinutes(now()) >= self::STALE_MINUTES;
        if ($stale && $this->repo->count() > 0) {
            $this->queueRefresh();
        }

        $goldQuotes = $this->repo->latestQuotes('gold');
        $silverQuotes = $this->repo->latestQuotes('silver');
        $sjc = $goldQuotes->where('source', GoldPrice::SOURCE_SJC);
        $btmc = $goldQuotes->where('source', GoldPrice::SOURCE_BTMC);

        // SJC quotes the same price at nearly every branch: say so once instead of repeating 12 rows
        $mainSjc = $sjc->first();
        $uniform = $mainSjc !== null && $sjc->every(fn ($q) => $q->buy_price === $mainSjc->buy_price && $q->sell_price === $mainSjc->sell_price);

        // Headline gold price: SJC bar if we trust a quote, else BTMC's own SJC line
        $headline = $mainSjc ?? $btmc->first(fn ($q) => str_contains(mb_strtoupper($q->product), 'SJC')) ?? $btmc->first();

        $headlineData = $headline ? $this->withChange($headline) : null;

        return [
            'has_data' => $goldQuotes->isNotEmpty(),
            'error' => $error,
            'synced_at' => $syncedAt,
            'stale' => $stale,
            'headline' => $headlineData,
            'sjc_branches' => $sjc->values(),
            'sjc_uniform' => $uniform,
            'btmc_gold' => $btmc->map(fn ($q) => $this->withChange($q))->values(),
            'silver' => $silverQuotes->map(fn ($q) => $this->withChange($q))->values(),
            'world' => $this->worldComparison($headlineData),
            'chart_options' => $this->chartOptions($goldQuotes),
            'alerts' => $this->alerts($this->newest($sjc), $this->newest($btmc->concat($silverQuotes))),
        ];
    }

    private function newest(Collection $quotes): ?Carbon
    {
        return $quotes->sortByDesc('quoted_at')->first()?->quoted_at;
    }

    /**
     * Warnings for a source that is not reaching us, so a stale price is never presented as today's (BTMC was dead for three weeks while the
     * page kept showing its 20 September quotes). Three rules, none of them guesses at "the market is closed":
     *  - the last recorded sync is older than SYNC_MAX_AGE_HOURS → the scheduler is not running;
     *  - the last sync recorded a failure for a source → that source's prices are the stored ones;
     *  - BTMC's newest publication is older than BTMC_MAX_AGE_HOURS → it is not publishing or not reaching us.
     *
     * @return array<int, array{source: string, message: string}>
     */
    private function alerts(?Carbon $sjcAt, ?Carbon $btmcAt): array
    {
        $status = Cache::get(self::STATUS_CACHE_KEY);
        $checkedAt = isset($status['checked_at']) ? Carbon::parse($status['checked_at']) : null;
        $fmt = fn (?Carbon $t) => $t ? $t->copy()->timezone(self::VN_TZ)->format('H:i d/m/Y') : null;
        $alerts = [];

        $syncStale = $checkedAt !== null && $checkedAt->diffInHours(now()) >= self::SYNC_MAX_AGE_HOURS;
        if ($syncStale) {
            $alerts[] = ['source' => 'sync', 'message' => 'Hệ thống chưa đồng bộ giá vàng từ '.$fmt($checkedAt).' (hơn '.self::SYNC_MAX_AGE_HOURS.' giờ), nên các giá bên dưới có thể đã cũ.'];
        }

        $errors = $syncStale ? [] : (array) ($status['errors'] ?? []);
        $checked = $fmt($checkedAt);

        if (isset($errors['sjc'])) {
            $alerts[] = ['source' => 'sjc', 'message' => "Lần cập nhật gần nhất ({$checked}) không lấy được giá vàng SJC; đang hiển thị giá đã lưu".($sjcAt ? ' lúc '.$fmt($sjcAt) : '').'.'];
        }

        if (isset($errors['btmc'])) {
            $alerts[] = ['source' => 'btmc', 'message' => "Lần cập nhật gần nhất ({$checked}) không lấy được giá Bảo Tín Minh Châu; bảng BTMC, bảng bạc và giá vàng thế giới ".($btmcAt ? 'đang là số đã lưu lúc '.$fmt($btmcAt) : 'chưa có số liệu nào được lưu').'.'];
        } elseif ($btmcAt !== null && $btmcAt->diffInHours(now()) >= self::BTMC_MAX_AGE_HOURS) {
            $alerts[] = ['source' => 'btmc', 'message' => 'Giá Bảo Tín Minh Châu lần cuối được ghi nhận lúc '.$fmt($btmcAt).', hơn '.intdiv(self::BTMC_MAX_AGE_HOURS, 24).' ngày chưa có giá mới: bảng BTMC, bảng bạc và giá vàng thế giới có thể đã cũ.'];
        }

        return $alerts;
    }

    /** @param array<string, string> $errors source => reason, empty when every source answered */
    private function recordStatus(array $errors): void
    {
        Cache::forever(self::STATUS_CACHE_KEY, ['checked_at' => now()->toIso8601String(), 'errors' => $errors]);
    }

    /**
     * Quote + change since the last quote of the previous Vietnam day.
     *
     * @return array{quote: GoldPrice, buy_change: ?int, sell_change: ?int, reference_at: ?Carbon}
     */
    private function withChange(GoldPrice $q): array
    {
        $startOfDay = Carbon::now(self::VN_TZ)->startOfDay()->utc();
        $prev = $this->repo->quoteBefore($q->source, $q->product, $q->branch, $startOfDay);

        // Nothing before today: compare with the day's first quote so a fresh install still shows movement
        if (! $prev) {
            $first = $this->repo->history($q->source, $q->product, $q->branch, $startOfDay)->first();
            $prev = ($first && $first->id !== $q->id) ? $first : null;
        }

        return [
            'quote' => $q,
            'buy_change' => $prev ? $q->buy_price - $prev->buy_price : null,
            'sell_change' => ($prev && $q->sell_price !== null && $prev->sell_price !== null) ? $q->sell_price - $prev->sell_price : null,
            'reference_at' => $prev?->quoted_at,
        ];
    }

    /**
     * World gold (USD/oz) converted to VND per luong at the Vietcombank USD rate, and how much the domestic
     * price is above it — the number people actually watch ("chênh lệch giá vàng trong nước - thế giới").
     *
     * @return array<string, mixed>|null
     */
    private function worldComparison(?array $headline): ?array
    {
        $world = $this->repo->latestWorldPrice();
        $usd = $this->rates->getLatestRate('USD');
        $usdVnd = $usd ? (float) str_replace(',', '', (string) $usd->sell) : 0.0;

        if (! $world || $usdVnd <= 0) {
            return $world ? ['usd_oz' => $world['price'], 'at' => $world['at'], 'vnd_luong' => null, 'usd_vnd' => null, 'premium_vnd' => null, 'premium_percent' => null] : null;
        }

        $vndPerLuong = $world['price'] * (GoldPrice::GRAMS_PER_LUONG / GoldPrice::GRAMS_PER_OUNCE) * $usdVnd;
        $domestic = $headline['quote']->sell_price ?? null;

        return [
            'usd_oz' => $world['price'],
            'at' => $world['at'],
            'usd_vnd' => $usdVnd,
            'vnd_luong' => round($vndPerLuong),
            'premium_vnd' => $domestic ? round($domestic - $vndPerLuong) : null,
            'premium_percent' => ($domestic && $vndPerLuong > 0) ? round(($domestic / $vndPerLuong - 1) * 100, 2) : null,
        ];
    }

    /** One entry per product the chart can show (id = a gold_prices row id, resolved back to product/branch in history()). */
    private function chartOptions($goldQuotes): array
    {
        $seen = [];
        $out = [];
        foreach ($goldQuotes as $q) {
            // SJC branches share a price: offer the first one only
            $key = $q->source . '|' . $q->product . ($q->source === GoldPrice::SOURCE_SJC ? '' : '|' . $q->branch);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['id' => $q->id, 'label' => ($q->source === GoldPrice::SOURCE_SJC ? 'SJC · ' : 'BTMC · ') . $q->display_name];
        }

        return $out;
    }

    // ── History for the chart ───────────────────────────────────────────────

    /**
     * @return array{points: array<int, array{t: int, buy: int, sell: ?int}>, range: string, label: string}|null null = unknown quote id
     */
    public function history(int $quoteId, string $range): ?array
    {
        $row = GoldPrice::find($quoteId);
        if (! $row) {
            return null;
        }

        $range = array_key_exists($range, self::RANGES) ? $range : '7D';
        $hours = self::RANGES[$range];
        $since = $hours === null ? Carbon::create(2000) : now()->subHours($hours);

        $points = $this->repo->history($row->source, $row->product, $row->branch, $since)
            ->map(fn (GoldPrice $q) => ['t' => $q->quoted_at->timestamp, 'buy' => $q->buy_price, 'sell' => $q->sell_price])
            ->values()
            ->all();

        // Carry the latest known price to "now" so a flat line still spans the window
        if ($points !== []) {
            $last = end($points);
            $nowTs = now()->timestamp;
            if ($last['t'] < $nowTs - 60) {
                $points[] = ['t' => $nowTs] + $last;
            }
        }

        return ['points' => $points, 'range' => $range, 'label' => $row->display_name];
    }

    // ── Sync ────────────────────────────────────────────────────────────────

    /**
     * Fetch SJC + BTMC + world price and store what is new.
     *
     * @return array{count: int, warnings: string[]}|array{error: string}
     */
    public function sync(): array
    {
        $data = $this->runScript();

        if ($data === null) {
            Log::warning('GoldPriceService: no JSON from get_gold_price.py');

            return ['error' => 'Không lấy được giá vàng (nguồn dữ liệu chậm hoặc lỗi kết nối).'];
        }
        // Which source failed is only known here: remember it, otherwise a dead source looks exactly like a quiet one
        $this->recordStatus(array_map('strval', (array) ($data['errors'] ?? [])));

        if (isset($data['error'])) {
            return ['error' => (string) $data['error']];
        }

        $fetchedAt = $data['fetched_at'] ?? now()->utc()->format('Y-m-d\TH:i:s\Z');
        $rows = [];

        foreach ($data['sjc'] ?? [] as $r) {
            $rows[] = [
                'source' => GoldPrice::SOURCE_SJC, 'metal' => 'gold', 'unit' => 'luong',
                'product' => $r['product'], 'branch' => $r['branch'],
                'buy_price' => (int) $r['buy'], 'sell_price' => (int) $r['sell'],
                'quoted_at' => $fetchedAt,
            ];
        }

        $btmc = $data['btmc'] ?? [];
        $latestAt = $btmc ? max(array_column($btmc, 'quoted_at')) : null;
        foreach ($btmc as $r) {
            $rows[] = [
                'source' => GoldPrice::SOURCE_BTMC, 'metal' => $r['metal'], 'unit' => $r['unit'],
                'product' => $r['product'], 'branch' => '', 'purity' => $r['purity'] ?? null,
                'buy_price' => (int) $r['buy'], 'sell_price' => isset($r['sell']) ? (int) $r['sell'] : null,
                // the world price belongs to the newest publication only
                'world_price' => ($r['quoted_at'] === $latestAt) ? ($data['world_usd_oz'] ?? null) : null,
                'quoted_at' => $r['quoted_at'],
            ];
        }

        return ['count' => $this->repo->saveQuotes($rows), 'warnings' => $data['warnings'] ?? []];
    }

    /** Manual "refresh now": rate-limited, and concurrent clicks share one Python run. */
    public function refresh(): array
    {
        if (! Cache::add('gold-refresh-cooldown', 1, self::MANUAL_COOLDOWN)) {
            return ['error' => 'Vừa cập nhật gần đây, vui lòng thử lại sau ít phút.', 'cooldown' => true];
        }

        return SingleFlight::run('gold-sync', fn () => null, fn () => $this->sync(), self::PYTHON_TIMEOUT + 30);
    }

    /** @return bool true when a job was queued (false = one is already pending) */
    public function queueRefresh(): bool
    {
        if (! Cache::add('gold-refresh-queued', 1, 300)) {
            return false;
        }

        SyncGoldPricesJob::dispatch();

        return true;
    }

    /** Protected so tests can stub the subprocess. */
    protected function runScript(): ?array
    {
        return PythonRunner::runAndDecodeJson(base_path('py/get_gold_price.py'), [], self::PYTHON_TIMEOUT);
    }
}
