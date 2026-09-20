<?php

namespace App\Frontend\Services;

use App\Frontend\Interfaces\StockRepositoryInterface;
use App\Frontend\Interfaces\WatchlistRepositoryInterface;
use App\Models\StockSymbol;
use App\Support\PriceUnit;

/**
 * A user's list of followed symbols, priced from the market snapshot (live during the session) and falling
 * back to the last stored close for symbols the snapshot does not carry.
 */
class WatchlistService
{
    public const MAX_ITEMS = 50;

    public function __construct(
        private readonly WatchlistRepositoryInterface $repo,
        private readonly MarketOverviewService $market,
        private readonly StockRepositoryInterface $stocks
    ) {}

    public static function normalize(string $symbol): ?string
    {
        $symbol = strtoupper(trim($symbol));

        return preg_match('/^[A-Z0-9]{2,12}$/', $symbol) ? $symbol : null;
    }

    /** @return string[] */
    public function symbols(int $userId): array
    {
        return $this->repo->symbols($userId);
    }

    public function isWatched(int $userId, string $symbol): bool
    {
        $symbol = self::normalize($symbol);

        return $symbol !== null && $this->repo->has($userId, $symbol);
    }

    /**
     * @return array{ok: true, symbol: string, name: ?string, added: bool}|array{ok: false, message: string, status: int}
     */
    public function add(int $userId, string $symbol): array
    {
        $symbol = self::normalize($symbol);
        if ($symbol === null) {
            return ['ok' => false, 'message' => 'Mã cổ phiếu không hợp lệ.', 'status' => 422];
        }

        $info = StockSymbol::where('symbol', $symbol)->first(['symbol', 'name']);
        if (! $info) {
            return ['ok' => false, 'message' => 'Không tìm thấy mã cổ phiếu này. Hãy chọn mã từ danh sách gợi ý.', 'status' => 422];
        }

        if ($this->repo->has($userId, $symbol)) {
            return ['ok' => true, 'symbol' => $symbol, 'name' => $info->name, 'added' => false];
        }
        if ($this->repo->count($userId) >= self::MAX_ITEMS) {
            return ['ok' => false, 'message' => 'Danh sách theo dõi tối đa ' . self::MAX_ITEMS . ' mã. Hãy bỏ bớt mã cũ trước khi thêm.', 'status' => 422];
        }

        $this->repo->add($userId, $symbol);

        return ['ok' => true, 'symbol' => $symbol, 'name' => $info->name, 'added' => true];
    }

    public function remove(int $userId, string $symbol): bool
    {
        $symbol = self::normalize($symbol);

        return $symbol !== null && $this->repo->remove($userId, $symbol);
    }

    /**
     * One row per followed symbol, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(int $userId, ?int $limit = null): array
    {
        $symbols = $this->repo->symbols($userId);
        if ($limit !== null) {
            $symbols = array_slice($symbols, 0, $limit);
        }
        if ($symbols === []) {
            return [];
        }

        $info = StockSymbol::whereIn('symbol', $symbols)->get(['symbol', 'name', 'exchange'])->keyBy('symbol');
        $live = $this->market->quotes($symbols);
        $missing = array_values(array_diff($symbols, array_keys($live)));
        $eod = $this->stocks->getLatestQuotes($missing);
        $history = $this->stocks->getCloseHistory($symbols, now()->subDays(45)->toDateString());
        $tradeDate = $live ? $this->market->tradeDate()?->toDateString() : null;

        $rows = [];
        foreach ($symbols as $symbol) {
            $row = [
                'symbol' => $symbol,
                'name' => $info[$symbol]->name ?? $symbol,
                'exchange' => $info[$symbol]->exchange ?? null,
                'price' => null, 'reference' => null, 'change' => null, 'percent' => null,
                'volume' => null, 'value' => null, 'at_ceiling' => false, 'at_floor' => false,
                'source' => null, 'as_of' => null,
                'spark' => $this->market->sparkPoints(array_slice(array_values($history[$symbol] ?? []), -20)),
            ];

            if (isset($live[$symbol])) {
                $q = $live[$symbol];
                $row = array_merge($row, [
                    'price' => $q['price'], 'reference' => $q['reference'], 'change' => $q['change'], 'percent' => $q['percent'],
                    'volume' => $q['volume'], 'value' => $q['value'], 'source' => 'live', 'as_of' => $tradeDate,
                    'at_ceiling' => $q['ceiling'] !== null && $q['price'] >= $q['ceiling'],
                    'at_floor' => $q['floor'] !== null && $q['price'] <= $q['floor'],
                ]);
            } elseif (isset($eod[$symbol])) {
                $price = PriceUnit::toVnd($eod[$symbol]['close']);
                $prev = PriceUnit::toVnd($eod[$symbol]['prev_close'] ?? null);
                $row = array_merge($row, [
                    'price' => (int) round($price), 'reference' => $prev !== null ? (int) round($prev) : null,
                    'change' => $prev !== null ? (int) round($price - $prev) : null,
                    'percent' => ($prev !== null && $prev > 0) ? round(($price / $prev - 1) * 100, 2) : null,
                    'source' => 'eod', 'as_of' => $eod[$symbol]['date'],
                ]);
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
