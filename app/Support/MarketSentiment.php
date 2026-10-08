<?php

namespace App\Support;

/**
 * "Tâm lý thị trường": one 0–100 reading (fear ... greed) put together from four things the home page already knows. It is OUR indicator,
 * not an official index, and the page says so next to the formula. Pure and deterministic.
 *
 *   Độ rộng        35 %  advancers vs decliners
 *   Đà chỉ số      30 %  VN-Index % change (±2 % is the whole scale)
 *   Trần / sàn     15 %  stocks at the ceiling vs at the floor (needs at least 5 of them, fewer is noise)
 *   Khối ngoại     20 %  net foreign buying as a share of foreign turnover (an estimate, see MarketOverviewService)
 *
 * A component with no data is left out and the weights of the others are re-scaled; with fewer than two components there is no reading.
 */
class MarketSentiment
{
    public const WEIGHTS = ['breadth' => 0.35, 'momentum' => 0.30, 'limits' => 0.15, 'foreign' => 0.20];

    /** The bands of the scale, upper bound exclusive: label and tone. */
    public const BANDS = [
        [20, 'Sợ hãi cực độ', 'down'],
        [40, 'Sợ hãi', 'down'],
        [60, 'Trung lập', 'flat'],
        [80, 'Tham lam', 'up'],
        [101, 'Tham lam cực độ', 'up'],
    ];

    /**
     * @param  array<string, mixed>  $overview  MarketOverviewService::overview()
     * @return array{score: int, label: string, tone: string, components: array<int, array<string, mixed>>, note: string}|null
     */
    public static function compute(array $overview): ?array
    {
        $parts = array_filter([
            self::breadth($overview['breadth'] ?? []),
            self::momentum($overview['indices'] ?? []),
            self::limits($overview['breadth'] ?? []),
            self::foreign($overview['foreign'] ?? null),
        ]);
        if (count($parts) < 2) {
            return null;
        }

        $weight = array_sum(array_column($parts, 'weight'));
        $score = (int) round(array_sum(array_map(fn (array $p) => $p['score'] * $p['weight'], $parts)) / $weight);
        [$label, $tone] = self::band($score);

        return [
            'score' => $score,
            'label' => $label,
            'tone' => $tone,
            'components' => array_values(array_map(fn (array $p) => ['key' => $p['key'], 'title' => $p['title'], 'score' => (int) round($p['score']), 'detail' => $p['detail'], 'weight' => (int) round($p['weight'] / $weight * 100)], $parts)),
            'note' => 'Chỉ báo tham khảo do Sun Stock AI tự tính từ độ rộng, đà VN-Index, trần/sàn và khối ngoại; không phải chỉ số chính thức, không phải khuyến nghị đầu tư.',
        ];
    }

    /** @return array{0: string, 1: string} */
    public static function band(int $score): array
    {
        foreach (self::BANDS as [$upper, $label, $tone]) {
            if ($score < $upper) {
                return [$label, $tone];
            }
        }

        return [self::BANDS[count(self::BANDS) - 1][1], self::BANDS[count(self::BANDS) - 1][2]];
    }

    private static function clamp(float $v): float
    {
        return max(0.0, min(100.0, $v));
    }

    private static function breadth(array $b): ?array
    {
        $adv = (int) ($b['advancers'] ?? 0);
        $dec = (int) ($b['decliners'] ?? 0);
        if ($adv + $dec <= 0) {
            return null;
        }

        return self::part('breadth', 'Độ rộng', self::clamp(50 + 50 * ($adv - $dec) / ($adv + $dec)), VnFormat::number($adv).' mã tăng / '.VnFormat::number($dec).' mã giảm');
    }

    private static function momentum(array $indices): ?array
    {
        $vn = collect($indices)->firstWhere('code', 'VNINDEX');
        if (! is_array($vn) || ! isset($vn['percent'])) {
            return null;
        }
        $pct = (float) $vn['percent'];

        return self::part('momentum', 'Đà chỉ số', self::clamp(50 + 25 * $pct), 'VN-Index '.VnFormat::percent($pct, 2, true));
    }

    private static function limits(array $b): ?array
    {
        $ceil = (int) ($b['ceiling'] ?? 0);
        $floor = (int) ($b['floor'] ?? 0);
        if ($ceil + $floor < 5) {
            return null;
        }

        return self::part('limits', 'Trần / sàn', self::clamp(50 + 50 * ($ceil - $floor) / ($ceil + $floor)), VnFormat::number($ceil).' mã trần / '.VnFormat::number($floor).' mã sàn');
    }

    private static function foreign(?array $f): ?array
    {
        $gross = (float) ($f['buy_value'] ?? 0) + (float) ($f['sell_value'] ?? 0);
        if ($f === null || $gross <= 0) {
            return null;
        }
        $net = (float) ($f['buy_value'] ?? 0) - (float) ($f['sell_value'] ?? 0);

        // net / turnover is rarely beyond ±20 %, so it is stretched ×2.5 to use the scale
        return self::part('foreign', 'Khối ngoại', self::clamp(50 + 125 * $net / $gross), 'Ròng '.VnFormat::bigMoney($net).' (ước tính)');
    }

    /** @return array{key: string, title: string, score: float, detail: string, weight: float} */
    private static function part(string $key, string $title, float $score, string $detail): array
    {
        return ['key' => $key, 'title' => $title, 'score' => $score, 'detail' => $detail, 'weight' => self::WEIGHTS[$key]];
    }
}
