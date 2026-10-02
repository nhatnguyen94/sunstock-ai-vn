<?php

namespace App\Support;

use App\Frontend\Services\StockPriceFreshness;

/**
 * Everything the header of the stock page shows, computed once from the stored price rows.
 *
 * The price feed is quoted in thousands of VND (ACB 22.05 = 22,050 ₫), index codes (VNINDEX…) in points. This class
 * converts to what a person expects to read — whole VND for a stock or ETF, points for an index — so the page, the
 * chart and the table all speak the same unit instead of printing "62 VNĐ" for a 62,100 ₫ share.
 *
 * Pure: rows in, array out.
 */
final class StockQuoteSummary
{
    /** Sessions in the "52-week" window. */
    public const YEAR_SESSIONS = 252;

    /** Sessions in the average-volume window. */
    public const AVG_VOLUME_SESSIONS = 20;

    /** Look-back for the return tiles, in calendar days. */
    public const RETURN_WINDOWS = ['1T' => 30, '3T' => 91, '6T' => 182, '1N' => 365];

    /** Multiplier from the feed's unit to the unit shown: 1000 for stocks and ETFs, 1 for an index. */
    public static function scaleFor(string $symbol): int
    {
        return in_array(strtoupper($symbol), StockPriceFreshness::INDICES, true) ? 1 : PriceUnit::FEED_TO_VND;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows  oldest first; time in epoch ms, open/high/low/close/volume in feed units
     * @return array<string, mixed>|null null when there is no usable close
     */
    public static function build(array $rows, string $symbol): ?array
    {
        $rows = array_values(array_filter($rows, fn ($r) => is_numeric($r['close'] ?? null) && (float) $r['close'] > 0));
        if ($rows === []) {
            return null;
        }

        $scale = self::scaleFor($symbol);
        $isIndex = $scale === 1;
        $last = $rows[count($rows) - 1];
        $prev = count($rows) >= 2 ? $rows[count($rows) - 2] : null;

        $close = (float) $last['close'];
        $change = $prev ? $close - (float) $prev['close'] : null;
        $changePct = ($prev && (float) $prev['close'] > 0) ? ($close / (float) $prev['close'] - 1) * 100 : null;

        $high = self::num($last['high'] ?? null);
        $low = self::num($last['low'] ?? null);

        $year = array_slice($rows, -self::YEAR_SESSIONS);
        $yearHigh = max(array_map(fn ($r) => self::num($r['high'] ?? null) ?? (float) $r['close'], $year));
        $yearLow = min(array_map(fn ($r) => self::num($r['low'] ?? null) ?? (float) $r['close'], $year));

        // average of the 20 sessions BEFORE the latest one, so a spike is compared with normal days, not with itself
        $avgVolume = count($rows) > 1 ? self::average(array_map(fn ($r) => (float) ($r['volume'] ?? 0), array_slice($rows, -self::AVG_VOLUME_SESSIONS - 1, -1))) : null;
        $volume = (float) ($last['volume'] ?? 0);

        return [
            'scale' => $scale,
            'is_index' => $isIndex,
            'unit' => $isIndex ? 'điểm' : '₫',
            'decimals' => $isIndex ? 2 : 0,
            'date' => date('Y-m-d', (int) floor(((int) $last['time']) / 1000) + 12 * 3600),
            'close' => $close * $scale,
            'prev_close' => $prev ? (float) $prev['close'] * $scale : null,
            'change' => $change === null ? null : $change * $scale,
            'change_pct' => $changePct,
            'direction' => self::direction($change),
            'open' => self::scaled($last['open'] ?? null, $scale),
            'high' => $high === null ? null : $high * $scale,
            'low' => $low === null ? null : $low * $scale,
            'day_position' => self::position($close, $low, $high),
            'year_high' => $yearHigh * $scale,
            'year_low' => $yearLow * $scale,
            'year_position' => self::position($close, $yearLow, $yearHigh),
            'volume' => $volume,
            'avg_volume' => $avgVolume,
            'volume_ratio' => ($avgVolume !== null && $avgVolume > 0) ? $volume / $avgVolume : null,
            'returns' => self::returns($rows),
        ];
    }

    private static function num(mixed $v): ?float
    {
        return is_numeric($v) && (float) $v > 0 ? (float) $v : null;
    }

    private static function scaled(mixed $v, int $scale): ?float
    {
        $n = self::num($v);

        return $n === null ? null : $n * $scale;
    }

    private static function direction(?float $change): string
    {
        return $change === null || abs($change) < 1e-9 ? 'flat' : ($change > 0 ? 'up' : 'down');
    }

    /** 0–100: where `$value` sits between `$low` and `$high`; null when the range is unknown or has no width. */
    private static function position(float $value, ?float $low, ?float $high): ?float
    {
        if ($low === null || $high === null || $high <= $low) {
            return null;
        }

        return max(0.0, min(100.0, ($value - $low) / ($high - $low) * 100));
    }

    /** @param list<float> $values */
    private static function average(array $values): ?float
    {
        return $values === [] ? null : array_sum($values) / count($values);
    }

    /**
     * Change of the close against the last close on or before (latest date − N days); null when the history does not reach back that far.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, float|null>
     */
    private static function returns(array $rows): array
    {
        $latest = $rows[count($rows) - 1];
        $latestMs = (int) $latest['time'];
        $close = (float) $latest['close'];
        $out = [];

        foreach (self::RETURN_WINDOWS as $label => $days) {
            $cutoff = $latestMs - $days * 86400 * 1000;
            $base = null;
            foreach ($rows as $row) {
                if ((int) $row['time'] <= $cutoff) {
                    $base = (float) $row['close'];
                } else {
                    break;
                }
            }
            $out[$label] = ($base !== null && $base > 0) ? ($close / $base - 1) * 100 : null;
        }

        return $out;
    }
}
