<?php

namespace App\Support;

/**
 * Risk and benchmark maths over a {@see PortfolioHistory} series. Pure.
 *
 * Returns are TIME-WEIGHTED: the day's external cash flow is taken out first, so buying more does not look like a
 * gain and selling does not look like a loss. Every statistic needs a minimum number of observations and is null
 * below it (a "volatility" from five days would be noise dressed up as a number).
 */
class PortfolioRisk
{
    public const TRADING_DAYS = 252;

    /** Fewer daily returns than this and volatility / beta are reported as unknown. */
    public const MIN_OBSERVATIONS = 20;

    /**
     * Daily time-weighted returns: `(value - flow) / previous value - 1`.
     *
     * @param  array<int, array{date: string, value: float, flow: float}> $series
     * @return array<string, float> date => return (fraction)
     */
    public static function dailyReturns(array $series): array
    {
        $out = [];
        $prev = null;
        foreach ($series as $p) {
            if ($prev !== null && $prev['value'] > 0) {
                $out[$p['date']] = ($p['value'] - $p['flow']) / $prev['value'] - 1;
            }
            $prev = $p;
        }

        return $out;
    }

    /**
     * @param  array<string, float> $returns
     * @return array{days: int, total_return: ?float, volatility: ?float, max_drawdown: ?float, best_day: ?array{date: string, percent: float}, worst_day: ?array{date: string, percent: float}}
     */
    public static function stats(array $returns): array
    {
        $n = count($returns);
        $empty = ['days' => $n, 'total_return' => null, 'volatility' => null, 'max_drawdown' => null, 'best_day' => null, 'worst_day' => null];
        if ($n === 0) {
            return $empty;
        }

        $index = 1.0;
        $peak = 1.0;
        $mdd = 0.0;
        foreach ($returns as $r) {
            $index *= 1 + $r;
            $peak = max($peak, $index);
            $mdd = min($mdd, $index / $peak - 1);
        }

        $best = array_keys($returns, max($returns))[0];
        $worst = array_keys($returns, min($returns))[0];

        return [
            'days' => $n,
            'total_return' => round(($index - 1) * 100, 2),
            'volatility' => $n >= self::MIN_OBSERVATIONS ? round(self::stdev(array_values($returns)) * sqrt(self::TRADING_DAYS) * 100, 2) : null,
            'max_drawdown' => round($mdd * 100, 2),
            'best_day' => ['date' => $best, 'percent' => round($returns[$best] * 100, 2)],
            'worst_day' => ['date' => $worst, 'percent' => round($returns[$worst] * 100, 2)],
        ];
    }

    /**
     * Beta of the portfolio against a benchmark over the days both have a return: how many % the portfolio tends to
     * move for a 1% move of the market. Null without enough common days or when the benchmark did not move.
     *
     * @param array<string, float> $portfolio
     * @param array<string, float> $benchmark
     */
    public static function beta(array $portfolio, array $benchmark): ?float
    {
        $common = array_intersect_key($portfolio, $benchmark);
        if (count($common) < self::MIN_OBSERVATIONS) {
            return null;
        }

        $p = array_values($common);
        $b = array_values(array_intersect_key($benchmark, $common));
        $meanP = array_sum($p) / count($p);
        $meanB = array_sum($b) / count($b);

        $cov = 0.0;
        $var = 0.0;
        foreach ($p as $i => $x) {
            $cov += ($x - $meanP) * ($b[$i] - $meanB);
            $var += ($b[$i] - $meanB) ** 2;
        }

        return $var > 0 ? round($cov / $var, 2) : null;
    }

    /**
     * Daily returns of a price series.
     *
     * @param  array<string, float> $closes date => close
     * @return array<string, float>
     */
    public static function priceReturns(array $closes): array
    {
        ksort($closes);
        $out = [];
        $prev = null;
        foreach ($closes as $date => $close) {
            if ($prev !== null && $prev > 0) {
                $out[$date] = $close / $prev - 1;
            }
            $prev = $close;
        }

        return $out;
    }

    /**
     * What the same cash flows would be worth if every buy had bought the benchmark instead (and every sell had
     * sold it), priced at the benchmark's close on or before that day. The fair "did I beat the market" comparison:
     * same money, same days, different choice.
     *
     * `covered` is false when the benchmark's history starts after the first flow, so the caller can say so.
     *
     * @param  array<int, array{date: string, value: float, flow: float}> $series
     * @param  array<string, float> $closes benchmark date => close (any unit, only ratios matter)
     * @return array{series: array<int, array{date: string, value: float}>, covered: bool, final_value: ?float}
     */
    public static function simulate(array $series, array $closes): array
    {
        ksort($closes);
        if ($series === [] || $closes === []) {
            return ['series' => [], 'covered' => false, 'final_value' => null];
        }

        $dates = array_keys($closes);
        $prices = array_values($closes);
        $cursor = 0;
        $price = $prices[0];
        $units = 0.0;
        $out = [];

        foreach ($series as $p) {
            while ($cursor < count($dates) && $dates[$cursor] <= $p['date']) {
                $price = $prices[$cursor];
                $cursor++;
            }
            if ($price > 0) {
                $units = max(0.0, $units + $p['flow'] / $price);
            }
            $out[] = ['date' => $p['date'], 'value' => round($units * $price, 2)];
        }

        return [
            'series' => $out,
            'covered' => $dates[0] <= self::addDays($series[0]['date'], 7),
            'final_value' => $out ? end($out)['value'] : null,
        ];
    }

    /**
     * Benchmark return in % between two dates, each priced at the close on or before it. Null when the history does
     * not reach back to the start date (an honest "unknown" beats a return measured over a shorter period).
     *
     * @param array<string, float> $closes date => close
     */
    public static function priceReturnBetween(array $closes, string $from, string $to): ?float
    {
        ksort($closes);
        $start = $end = null;
        foreach ($closes as $date => $close) {
            if ($date <= $from) {
                $start = $close;
            }
            if ($date <= $to) {
                $end = $close;
            }
        }

        return ($start !== null && $end !== null && $start > 0) ? round(($end / $start - 1) * 100, 2) : null;
    }

    /** @param float[] $xs */
    private static function stdev(array $xs): float
    {
        $n = count($xs);
        if ($n < 2) {
            return 0.0;
        }
        $mean = array_sum($xs) / $n;

        return sqrt(array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $xs)) / ($n - 1));
    }

    private static function addDays(string $date, int $days): string
    {
        return date('Y-m-d', strtotime($date . " +{$days} days"));
    }
}
