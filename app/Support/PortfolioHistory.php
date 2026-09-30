<?php

namespace App\Support;

/**
 * Replays a portfolio's LEDGER (every buy and sell) against the daily closes, so the value line is right even after
 * partial sales and fully closed positions. The older {@see PortfolioPerformance} works from today's holdings only:
 * it pretends the current quantity was held since the buy date, which overstates a portfolio that sold something and
 * drops a closed position from history altogether.
 *
 * Besides the value it records each day's external cash `flow` (money put in by buys, taken out by sells), which is
 * what makes fair comparisons possible: a time-weighted return that ignores deposits, and "the same flows invested in
 * an index". Pure: arrays in, arrays out, no DB.
 */
class PortfolioHistory
{
    /**
     * @param array<int, array{date: string, type: string, symbol: string, quantity: int|float, price: int|float, fee?: int|float}> $transactions
     *        whole-VND prices; any order (they are sorted by date, ties keep their given order)
     * @param array<string, array<string, float>> $closes daily closes in FEED units: [symbol => [Y-m-d => close]]
     * @return array<int, array{date: string, value: float, invested: float, flow: float}> ascending, VND
     */
    public static function series(array $transactions, array $closes): array
    {
        if ($transactions === []) {
            return [];
        }

        $byDate = [];
        foreach (array_values($transactions) as $i => $t) {
            $byDate[$t['date']][] = $t + ['i' => $i];
        }
        ksort($byDate);
        $start = array_key_first($byDate);

        $dates = array_fill_keys(array_keys($byDate), true);
        $symbols = array_unique(array_column($transactions, 'symbol'));
        foreach ($symbols as $sym) {
            foreach (array_keys($closes[$sym] ?? []) as $d) {
                if ($d >= $start) {
                    $dates[$d] = true;
                }
            }
        }
        $dates = array_keys($dates);
        sort($dates);

        $qty = [];    // symbol => shares held
        $cost = [];   // symbol => cost basis of the shares held (fees included)
        $last = [];   // symbol => last known price (VND), carried across days without a close
        $out = [];

        foreach ($dates as $date) {
            $flow = 0.0;

            foreach ($byDate[$date] ?? [] as $t) {
                $sym = $t['symbol'];
                $q = (float) $t['quantity'];
                $gross = $q * (float) $t['price'];
                $fee = (float) ($t['fee'] ?? 0);

                if ($t['type'] === 'sell') {
                    $held = $qty[$sym] ?? 0.0;
                    $sold = min($q, $held);
                    if ($held > 0) {
                        $cost[$sym] = ($cost[$sym] ?? 0.0) * (1 - $sold / $held);
                    }
                    $qty[$sym] = $held - $sold;
                    if ($qty[$sym] <= 0) {
                        unset($qty[$sym], $cost[$sym]);
                    }
                    $flow -= $gross - $fee;
                } else {
                    $qty[$sym] = ($qty[$sym] ?? 0.0) + $q;
                    $cost[$sym] = ($cost[$sym] ?? 0.0) + $gross + $fee;
                    $flow += $gross + $fee;
                }
                // On the day of a trade the trade price is the freshest thing we know, until a close overrides it below
                $last[$sym] = (float) $t['price'];
            }

            foreach ($symbols as $sym) {
                if (isset($closes[$sym][$date])) {
                    $last[$sym] = PriceUnit::toVnd($closes[$sym][$date]);
                }
            }

            $value = 0.0;
            foreach ($qty as $sym => $q) {
                $value += $q * ($last[$sym] ?? 0.0);
            }

            $out[] = ['date' => $date, 'value' => round($value, 2), 'invested' => round(array_sum($cost), 2), 'flow' => round($flow, 2)];
        }

        return $out;
    }

    /** Fewer points for the chart (the flows are only needed by the maths, which uses the full series). */
    public static function forChart(array $series): array
    {
        return array_map(fn (array $p) => ['date' => $p['date'], 'value' => $p['value'], 'invested' => $p['invested']], PortfolioPerformance::thin($series));
    }
}
