<?php

namespace App\Backend\Services;

use App\Models\PortfolioItem;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Models\StockSymbol;
use App\Models\WatchlistItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Checks on the data the whole site stands on, each with a count and a short sample so an admin can act: tracked stocks without a single
 * price, stocks whose newest price is old, price rows that cannot be right, and symbols people follow or hold that the system does not know.
 * The price table is big (millions of rows): every query is bounded (an index on stock_id/date, a recent date window or a sample limit),
 * and the whole report is cached for ten minutes ("Kiểm tra lại" clears it).
 */
class DataQualityService
{
    public const CACHE_KEY = 'admin:data-quality';

    /** A stock without a price for this many days is "stale" (a long holiday is 9 days; a suspended stock is stale for good). */
    public const STALE_DAYS = 10;

    /** Rows of the last N days are checked for impossible values (a full scan of the whole history is not needed to catch a bad feed). */
    public const INVALID_WINDOW_DAYS = 30;

    private const SAMPLE = 15;

    /**
     * @return array{generated_at: string, checks: array<int, array{key: string, title: string, hint: string, count: int, sample: array<int, string>}>}
     */
    public function report(): array
    {
        return Cache::remember(self::CACHE_KEY, 600, fn () => [
            'generated_at' => now()->toIso8601String(),
            'checks' => [
                $this->withoutPrices(),
                $this->stale(),
                $this->invalidRows(),
                $this->unknownSymbols(),
            ],
        ]);
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function check(string $key, string $title, string $hint, int $count, array $sample): array
    {
        return compact('key', 'title', 'hint', 'count', 'sample');
    }

    private function withoutPrices(): array
    {
        $query = Stock::query()->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('stock_prices')->whereColumn('stock_prices.stock_id', 'stocks.id'));

        return $this->check(
            'no-prices',
            'Cổ phiếu được theo dõi nhưng chưa có giá nào',
            'Chạy sync:stock-prices hoặc xóa mã nếu nó không còn niêm yết.',
            (clone $query)->count(),
            (clone $query)->orderBy('symbol')->limit(self::SAMPLE)->pluck('symbol')->all(),
        );
    }

    private function stale(): array
    {
        $cutoff = now()->subDays(self::STALE_DAYS)->toDateString();
        $query = StockPrice::query()
            ->join('stocks', 'stocks.id', '=', 'stock_prices.stock_id')
            ->select('stocks.symbol', DB::raw('max(stock_prices.date) as last_date'))
            ->groupBy('stocks.symbol')
            ->havingRaw('max(stock_prices.date) < ?', [$cutoff]);

        $rows = $query->orderBy('last_date')->get();

        return $this->check(
            'stale',
            'Cổ phiếu có giá mới nhất đã cũ (hơn '.self::STALE_DAYS.' ngày)',
            'Có thể mã bị đình chỉ / hủy niêm yết, hoặc nguồn giá bỏ sót nó.',
            $rows->count(),
            $rows->take(self::SAMPLE)->map(fn ($r) => "{$r->symbol} (".substr((string) $r->last_date, 0, 10).')')->all(),
        );
    }

    private function invalidRows(): array
    {
        $query = StockPrice::query()
            ->join('stocks', 'stocks.id', '=', 'stock_prices.stock_id')
            ->where('stock_prices.date', '>=', now()->subDays(self::INVALID_WINDOW_DAYS)->toDateString())
            ->where(fn ($q) => $q->where('stock_prices.close', '<=', 0)->orWhere('stock_prices.open', '<=', 0)->orWhereColumn('stock_prices.high', '<', 'stock_prices.low'));

        return $this->check(
            'invalid',
            'Dòng giá bất thường ('.self::INVALID_WINDOW_DAYS.' ngày qua)',
            'Giá đóng/mở bằng hoặc dưới 0, hoặc giá cao nhất thấp hơn giá thấp nhất — thường do lỗi nguồn dữ liệu.',
            (clone $query)->count(),
            (clone $query)->orderByDesc('stock_prices.date')->limit(self::SAMPLE)->get(['stocks.symbol', 'stock_prices.date'])->map(fn ($r) => "{$r->symbol} (".substr((string) $r->date, 0, 10).')')->all(),
        );
    }

    private function unknownSymbols(): array
    {
        $known = StockSymbol::query()->select('symbol');
        $watched = WatchlistItem::query()->whereNotIn('symbol', $known)->distinct()->pluck('symbol');
        $held = PortfolioItem::query()->whereNotIn('stock_symbol', $known)->distinct()->pluck('stock_symbol');
        $all = $watched->merge($held)->unique()->sort()->values();

        return $this->check(
            'unknown-symbols',
            'Mã người dùng theo dõi / nắm giữ nhưng hệ thống không biết',
            'Chạy sync:stock-data để nạp danh sách mã; nếu mã đã bị hủy niêm yết, báo người dùng.',
            $all->count(),
            $all->take(self::SAMPLE)->all(),
        );
    }
}
