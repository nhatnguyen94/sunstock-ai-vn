<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Pure performance maths for a listed fund's PRICE history (no NAV is available for ETFs). Rows are
 * `[date 'Y-m-d', close (feed unit, thousands of VND), volume]`, ascending by date. Reuses {@see FundMetrics} for
 * return / drawdown / volatility and adds what a traded fund needs: YTD, liquidity and the 52-week range.
 */
class EtfMetrics
{
    /** Windows shown to users, in order. */
    public const WINDOWS = ['1M', '3M', '6M', '1Y', '3Y'];

    /** A window counts only if the history really covers it (a few days of slack for weekends/holidays). */
    private const COVERAGE_SLACK_DAYS = 10;

    /**
     * Return / max drawdown / volatility over a calendar window, or all nulls when the fund is younger than that
     * window (a "3Y" figure for a fund listed 8 months ago would silently be an 8-month figure).
     *
     * @param  array<int, array{0:string,1:float,2?:int}> $rows
     * @return array{return_pct:?float, max_drawdown:?float, volatility:?float, points:int}
     */
    public static function stats(array $rows, string $window): array
    {
        $empty = ['return_pct' => null, 'max_drawdown' => null, 'volatility' => null, 'points' => 0];
        $days = FundMetrics::WINDOWS[$window] ?? null;

        if ($rows === []) {
            return $empty;
        }
        if ($days !== null) {
            $last = Carbon::parse(end($rows)[0]);
            if (Carbon::parse($rows[0][0])->gt($last->copy()->subDays($days - self::COVERAGE_SLACK_DAYS))) {
                return $empty;
            }
        }

        return FundMetrics::windowStats(self::closes($rows), $window);
    }

    /** Return since the last close of the previous calendar year; null for a fund listed during the current year. */
    public static function ytd(array $rows): ?float
    {
        if (count($rows) < 2) {
            return null;
        }
        $year = Carbon::parse(end($rows)[0])->year;
        $base = null;
        foreach ($rows as $row) {
            if (Carbon::parse($row[0])->year < $year) {
                $base = $row[1];
            }
        }
        // Listed during this year: there is no year-end close to measure from, and "since listing" is not YTD.
        if ($base === null) {
            return null;
        }

        return $base > 0 ? round((end($rows)[1] / $base - 1) * 100, 2) : null;
    }

    /**
     * Average traded value per session over the last `$sessions` bars, in whole VND (close × PriceUnit::FEED_TO_VND × volume).
     * The liquidity number that matters for an ETF: how much can be bought or sold without moving the price.
     */
    public static function averageValue(array $rows, int $sessions = 20): ?int
    {
        $tail = array_slice($rows, -$sessions);
        $values = [];
        foreach ($tail as $row) {
            $volume = (int) ($row[2] ?? 0);
            if ($volume > 0 && $row[1] > 0) {
                $values[] = $row[1] * PriceUnit::FEED_TO_VND * $volume;
            }
        }

        return $values ? (int) round(array_sum($values) / count($values)) : null;
    }

    /**
     * Lowest / highest close of the last 52 weeks, whole VND.
     *
     * @return array{low:?int, high:?int}
     */
    public static function range52w(array $rows): array
    {
        if ($rows === []) {
            return ['low' => null, 'high' => null];
        }
        $cutoff = Carbon::parse(end($rows)[0])->subDays(365)->toDateString();
        $closes = [];
        foreach ($rows as $row) {
            if ($row[0] >= $cutoff && $row[1] > 0) {
                $closes[] = $row[1];
            }
        }

        return $closes
            ? ['low' => (int) round(min($closes) * PriceUnit::FEED_TO_VND), 'high' => (int) round(max($closes) * PriceUnit::FEED_TO_VND)]
            : ['low' => null, 'high' => null];
    }

    /** @return array<int, array{0:string,1:float}> */
    private static function closes(array $rows): array
    {
        return array_map(fn (array $r) => [$r[0], (float) $r[1]], $rows);
    }
}
