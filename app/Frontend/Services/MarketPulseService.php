<?php

namespace App\Frontend\Services;

use App\Frontend\Interfaces\ExchangeRateRepositoryInterface;
use App\Frontend\Interfaces\FundRepositoryInterface;
use App\Frontend\Interfaces\GoldPriceRepositoryInterface;
use App\Models\Fund;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * The "Vàng · Tỷ giá · Quỹ" card on the home page: a handful of numbers from tables the site already fills (gold quotes, Vietcombank rates,
 * open-ended funds). Read-only and DB-only — it never starts a sync, a queue job or Python, so it cannot slow the home page; each part
 * is independent and simply null when its table is empty or fails.
 */
class MarketPulseService
{
    private const VN_TZ = 'Asia/Ho_Chi_Minh';

    public function __construct(
        private readonly GoldPriceRepositoryInterface $gold,
        private readonly ExchangeRateRepositoryInterface $rates,
        private readonly FundRepositoryInterface $funds
    ) {}

    /** @return array{gold: ?array, usd: ?array, funds: array<int, array<string, mixed>>} */
    public function build(): array
    {
        return [
            'gold' => $this->safely(fn () => $this->goldPart()),
            'usd' => $this->safely(fn () => $this->usdPart()),
            'funds' => $this->safely(fn () => $this->fundsPart(3)) ?? [],
        ];
    }

    private function safely(callable $part): mixed
    {
        try {
            return $part();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** SJC bar (else the first gold quote): sell/buy price per luong, change since the last quote of the previous Vietnam day, and the world price. */
    private function goldPart(): ?array
    {
        $quotes = $this->gold->latestQuotes('gold');
        $head = $quotes->first(fn ($q) => $q->source === 'SJC') ?? $quotes->first();
        if ($head === null) {
            return null;
        }

        $price = $head->sell_price ?? $head->buy_price;
        $startOfDay = Carbon::now(self::VN_TZ)->startOfDay()->utc();
        $prev = $this->gold->quoteBefore($head->source, $head->product, $head->branch, $startOfDay);
        $prevPrice = $prev ? ($prev->sell_price ?? $prev->buy_price) : null;
        $change = ($price !== null && $prevPrice !== null) ? $price - $prevPrice : null;
        $world = $this->gold->latestWorldPrice();

        return [
            'name' => $head->source === 'SJC' ? 'Vàng SJC' : 'Vàng '.$head->source,
            'price' => $price,
            'buy' => $head->buy_price,
            'change' => $change,
            'percent' => ($change !== null && $prevPrice > 0) ? $change / $prevPrice * 100 : null,
            'unit' => 'lượng',
            'world_usd' => $world['price'] ?? null,
            'quoted_at' => $head->quoted_at?->toIso8601String(),
        ];
    }

    /** Vietcombank USD (sell) and its change against the previous published day. */
    private function usdPart(): ?array
    {
        $latest = $this->rates->getLatestRate('USD');
        if ($latest === null) {
            return null;
        }

        $sell = $this->rate($latest->sell);
        if ($sell === null) {
            return null;
        }
        $prev = $this->rates->getRateBefore('USD', (string) $latest->date);
        $prevSell = $prev ? $this->rate($prev->sell) : null;
        $change = $prevSell !== null ? $sell - $prevSell : null;

        return [
            'sell' => $sell,
            'buy' => $this->rate($latest->buy_transfer),
            'change' => $change,
            'percent' => ($change !== null && $prevSell > 0) ? $change / $prevSell * 100 : null,
            'date' => (string) $latest->date,
        ];
    }

    /**
     * The best equity funds over the last 12 months (funds younger than that have no figure and never outrank real ones).
     *
     * @return array<int, array{code: string, name: string, percent: float}>
     */
    private function fundsPart(int $limit): array
    {
        return $this->funds->search(['type' => Fund::TYPE_STOCK, 'sort' => 'nav_change_12m', 'dir' => 'desc'])
            ->filter(fn (Fund $f) => $f->nav_change_12m !== null)
            ->take($limit)
            ->map(fn (Fund $f) => ['code' => $f->short_name, 'name' => $f->name, 'percent' => (float) $f->nav_change_12m])
            ->values()
            ->all();
    }

    /** "26,100.00" / "26100" / "-" -> 26100.0, or null when there is no number. */
    private function rate(?string $value): ?float
    {
        $v = str_replace(',', '', trim((string) $value));

        return is_numeric($v) && (float) $v > 0 ? (float) $v : null;
    }
}
