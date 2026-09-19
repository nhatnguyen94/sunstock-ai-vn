<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Pure performance maths over a fund's NAV series — no I/O, so it is unit-testable
 * without Python or a database. Input is the `nav` array produced by
 * py/get_fund_detail.py: [["YYYY-MM-DD", navPerUnit], ...] in ascending date order.
 */
class FundMetrics
{
    /** Window key => calendar days looked back from the latest NAV date (null = whole history). */
    public const WINDOWS = [
        '1M'  => 30,
        '3M'  => 91,
        '6M'  => 182,
        '1Y'  => 365,
        '3Y'  => 1095,
        'ALL' => null,
    ];

    /**
     * Points inside a window, ascending. The whole series for 'ALL' / unknown keys.
     *
     * @param  array<int, array{0:string,1:float}> $nav
     * @return array<int, array{0:string,1:float}>
     */
    public static function slice(array $nav, string $window): array
    {
        $days = self::WINDOWS[$window] ?? null;
        if ($days === null || $nav === []) {
            return array_values($nav);
        }

        $cutoff = Carbon::parse(end($nav)[0])->subDays($days)->toDateString();

        return array_values(array_filter($nav, fn (array $p) => $p[0] >= $cutoff));
    }

    /**
     * Stats for one window.
     *
     * - return_pct:   first -> last NAV in the window, in %
     * - max_drawdown: worst peak-to-trough fall in the window, in % (<= 0)
     * - volatility:   annualised standard deviation of period returns, in %.
     *                 Null for 'ALL' (history is thinned to weekly points before the
     *                 daily window, so the sampling frequency is not uniform) and for
     *                 windows with fewer than 3 points.
     *
     * @param  array<int, array{0:string,1:float}> $nav
     * @return array{return_pct:?float, max_drawdown:?float, volatility:?float, points:int}
     */
    public static function windowStats(array $nav, string $window): array
    {
        $pts = self::slice($nav, $window);
        $n   = count($pts);

        $empty = ['return_pct' => null, 'max_drawdown' => null, 'volatility' => null, 'points' => $n];
        if ($n < 2 || $pts[0][1] <= 0) {
            return $empty;
        }

        $first = $pts[0][1];
        $last  = $pts[$n - 1][1];

        // Max drawdown
        $peak = $first;
        $mdd  = 0.0;
        foreach ($pts as [, $v]) {
            $peak = max($peak, $v);
            if ($peak > 0) {
                $mdd = min($mdd, $v / $peak - 1);
            }
        }

        return [
            'return_pct'   => round(($last / $first - 1) * 100, 2),
            'max_drawdown' => round($mdd * 100, 2),
            'volatility'   => $window === 'ALL' ? null : self::annualisedVolatility($pts),
            'points'       => $n,
        ];
    }

    /**
     * @param  array<int, array{0:string,1:float}> $nav
     * @return array<string, array{return_pct:?float, max_drawdown:?float, volatility:?float, points:int}>
     */
    public static function allWindows(array $nav): array
    {
        $out = [];
        foreach (array_keys(self::WINDOWS) as $w) {
            $out[$w] = self::windowStats($nav, $w);
        }

        return $out;
    }

    /**
     * Annualised volatility (%) from period returns. The annualisation factor is
     * estimated from the series itself (points per year), because open-ended funds
     * do not all publish a NAV every trading day — some are weekly.
     *
     * @param array<int, array{0:string,1:float}> $pts
     */
    private static function annualisedVolatility(array $pts): ?float
    {
        $n = count($pts);
        if ($n < 3) {
            return null;
        }

        $returns = [];
        for ($i = 1; $i < $n; $i++) {
            if ($pts[$i - 1][1] > 0) {
                $returns[] = $pts[$i][1] / $pts[$i - 1][1] - 1;
            }
        }
        if (count($returns) < 2) {
            return null;
        }

        $spanDays = Carbon::parse($pts[0][0])->diffInDays(Carbon::parse($pts[$n - 1][0]));
        if ($spanDays <= 0) {
            return null;
        }
        $perYear = ($n - 1) / ($spanDays / 365.25);

        $mean = array_sum($returns) / count($returns);
        $var  = array_sum(array_map(fn ($r) => ($r - $mean) ** 2, $returns)) / (count($returns) - 1);

        return round(sqrt($var) * sqrt($perYear) * 100, 2);
    }
}
