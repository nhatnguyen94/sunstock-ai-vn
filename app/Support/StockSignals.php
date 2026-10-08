<?php

namespace App\Support;

/**
 * "Tín hiệu hôm nay": which stocks stand out right now, found by comparing TODAY'S quote (market snapshot, whole VND) with the stored daily
 * history (feed units, thousands of VND — converted here). Pure: all data comes in as arguments, nothing is read or written.
 *
 *   breakout    today's price is above the highest high of the stored window (about a year)
 *   breakdown   today's price is below the lowest low of that window
 *   volume      today's volume is at least twice the average of the last 20 stored sessions (and enough money changed hands)
 *   overbought  RSI(14) of the stored closes + today's price is 70 or more
 *   oversold    RSI(14) is 30 or less
 *
 * These are descriptions of what happened, not recommendations. A stock needs enough history and recent bars, and enough traded value today,
 * to be considered at all: thin or stale data produces no signal rather than a noisy one.
 */
class StockSignals
{
    public const MIN_VALUE = 3_000_000_000;        // VND traded today for any signal

    public const MIN_VOLUME_VALUE = 5_000_000_000; // ... and for a volume spike

    public const MIN_SESSIONS = 100;               // stored sessions in the window before a high/low means anything

    public const MAX_STALE_DAYS = 10;              // the last stored bar must be this recent

    public const VOLUME_RATIO = 2.0;

    public const MIN_VOLUME_BARS = 10;

    public const RSI_PERIOD = 14;

    public const RSI_HIGH = 70.0;

    public const RSI_LOW = 30.0;

    public const LIST_LIMIT = 6;

    private const STOCK_RE = '/^[A-Z][A-Z0-9]{2}$/';   // real stocks only: no ETFs, covered warrants or bonds

    /**
     * Wilder's RSI of the last closes; null with fewer than period + 1 of them.
     *
     * @param  array<int, int|float>  $closes  oldest first
     */
    public static function rsi(array $closes, int $period = self::RSI_PERIOD): ?float
    {
        $closes = array_values(array_map('floatval', $closes));
        if (count($closes) < $period + 1) {
            return null;
        }

        $gain = $loss = 0.0;
        for ($i = 1; $i <= $period; $i++) {
            $d = $closes[$i] - $closes[$i - 1];
            $gain += max($d, 0);
            $loss += max(-$d, 0);
        }
        $gain /= $period;
        $loss /= $period;

        for ($i = $period + 1, $n = count($closes); $i < $n; $i++) {
            $d = $closes[$i] - $closes[$i - 1];
            $gain = ($gain * ($period - 1) + max($d, 0)) / $period;
            $loss = ($loss * ($period - 1) + max(-$d, 0)) / $period;
        }

        if ($loss == 0.0) {
            return $gain == 0.0 ? 50.0 : 100.0;
        }

        return 100 - 100 / (1 + $gain / $loss);
    }

    /**
     * @param  array<string, array<int, mixed>>  $quotes  snapshot map {symbol: [price, ref, %, volume, value, ...]}, whole VND
     * @param  array<string, array{n: int, high: float|int, low: float|int, last_date: string}>  $history  stored window per symbol, FEED units
     * @param  array<string, array<int, array{0: string, 1: float|int, 2: float|int}>>  $bars  recent stored [date, close, volume] per symbol, oldest first, FEED units
     * @return array{universe: int, breakout: array, breakdown: array, volume: array, overbought: array, oversold: array}
     */
    public static function evaluate(array $quotes, array $history, array $bars, string $today): array
    {
        $out = ['universe' => 0, 'breakout' => [], 'breakdown' => [], 'volume' => [], 'overbought' => [], 'oversold' => []];
        $oldest = date('Y-m-d', strtotime($today.' -'.self::MAX_STALE_DAYS.' days'));

        foreach ($quotes as $symbol => $q) {
            $symbol = (string) $symbol;
            if (! preg_match(self::STOCK_RE, $symbol) || ! is_array($q) || ! isset($q[0], $q[2], $q[3], $q[4])) {
                continue;
            }
            [$price, $pct, $volume, $value] = [(float) $q[0], (float) $q[2], (float) $q[3], (float) $q[4]];
            $h = $history[$symbol] ?? null;
            if ($price <= 0 || $value < self::MIN_VALUE || $h === null || $h['n'] < self::MIN_SESSIONS || $h['last_date'] < $oldest) {
                continue;
            }
            $out['universe']++;
            $row = ['s' => $symbol, 'p' => (int) round($price), 'c' => round($pct, 2), 'v' => (int) $value];

            $high = PriceUnit::toVnd($h['high']);
            $low = PriceUnit::toVnd($h['low']);
            if ($price > $high && $pct > 0) {
                $out['breakout'][] = $row + ['ref' => (int) round($high), 'over' => round(($price / $high - 1) * 100, 2)];
            } elseif ($price < $low && $pct < 0) {
                $out['breakdown'][] = $row + ['ref' => (int) round($low), 'under' => round(($low / $price - 1) * 100, 2)];
            }

            $series = $bars[$symbol] ?? [];
            $recentVolumes = array_slice(array_column($series, 2), -20);
            if (count($recentVolumes) >= self::MIN_VOLUME_BARS && $value >= self::MIN_VOLUME_VALUE) {
                $avg = array_sum($recentVolumes) / count($recentVolumes);
                if ($avg > 0 && $volume >= self::VOLUME_RATIO * $avg) {
                    $out['volume'][] = $row + ['ratio' => round($volume / $avg, 1)];
                }
            }

            $closes = array_map(fn ($b) => PriceUnit::toVnd($b[1]), array_slice($series, -40));
            $closes[] = $price;
            $rsi = self::rsi($closes);
            if ($rsi !== null && $rsi >= self::RSI_HIGH) {
                $out['overbought'][] = $row + ['rsi' => round($rsi, 1)];
            } elseif ($rsi !== null && $rsi <= self::RSI_LOW) {
                $out['oversold'][] = $row + ['rsi' => round($rsi, 1)];
            }
        }

        usort($out['breakout'], fn ($a, $b) => $b['over'] <=> $a['over']);
        usort($out['breakdown'], fn ($a, $b) => $b['under'] <=> $a['under']);
        usort($out['volume'], fn ($a, $b) => $b['ratio'] <=> $a['ratio']);
        usort($out['overbought'], fn ($a, $b) => $b['rsi'] <=> $a['rsi']);
        usort($out['oversold'], fn ($a, $b) => $a['rsi'] <=> $b['rsi']);

        foreach (['breakout', 'breakdown', 'volume', 'overbought', 'oversold'] as $key) {
            $out[$key.'_total'] = count($out[$key]);
            $out[$key] = array_slice($out[$key], 0, self::LIST_LIMIT);
        }

        return $out;
    }
}
