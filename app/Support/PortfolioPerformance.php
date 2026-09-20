<?php

namespace App\Support;

/**
 * Reconstructs how a portfolio's value evolved from the holdings' buy dates and the synced daily closes —
 * so the performance chart is available on day one (no waiting for snapshots to accumulate).
 *
 * Assumption (stated in the UI): quantities are held unchanged since each buy date. There is no
 * transaction ledger, so partial sells / later top-ups edited in place are not replayed.
 *
 * Pure: arrays in, arrays out — no DB, so it is unit-testable.
 */
class PortfolioPerformance
{
    /** More points than this add nothing visible on a chart; history is thinned to at most this many. */
    public const MAX_POINTS = 400;

    /**
     * @param array<int, array{symbol: string, quantity: int|float, buy_price: int|float, buy_date: string}> $holdings
     * @param array<string, array<string, float>> $closes daily closes in FEED units: [symbol => [Y-m-d => close]]
     * @return array<int, array{date: string, value: float, invested: float}> ascending, VND
     */
    public static function series(array $holdings, array $closes): array
    {
        if ($holdings === []) {
            return [];
        }

        $start = min(array_column($holdings, 'buy_date'));

        // Every trading date any holding has a close for, from the first buy onwards
        $dates = [];
        foreach ($closes as $byDate) {
            foreach (array_keys($byDate) as $d) {
                if ($d >= $start) {
                    $dates[$d] = true;
                }
            }
        }
        $dates[$start] = true;
        $dates = array_keys($dates);
        sort($dates);

        $last = [];   // symbol => last known price (VND), carried forward across gaps
        $out = [];

        foreach ($dates as $date) {
            $value = 0.0;
            $invested = 0.0;

            foreach ($holdings as $h) {
                if ($h['buy_date'] > $date) {
                    continue;   // not owned yet
                }

                $sym = $h['symbol'];
                if (isset($closes[$sym][$date])) {
                    $last[$sym] = PriceUnit::toVnd($closes[$sym][$date]);
                }

                // Before the first close we know of, the best honest estimate is what it was bought for
                $price = $last[$sym] ?? (float) $h['buy_price'];
                $value += $h['quantity'] * $price;
                $invested += $h['quantity'] * $h['buy_price'];
            }

            $out[] = ['date' => $date, 'value' => round($value, 2), 'invested' => round($invested, 2)];
        }

        return self::thin($out);
    }

    /** Keep the first/last point and an evenly spaced subset in between. */
    public static function thin(array $points, int $max = self::MAX_POINTS): array
    {
        $n = count($points);
        if ($n <= $max) {
            return $points;
        }

        $step = ($n - 1) / ($max - 1);
        $out = [];
        for ($i = 0; $i < $max; $i++) {
            $out[] = $points[(int) round($i * $step)];
        }

        return $out;
    }
}
