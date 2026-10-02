<?php

namespace App\Support;

/**
 * Pure shaping of the market heat map: which stocks are shown and what the page needs to know about each.
 *
 * Input is the snapshot's quote map `{symbol: [price, reference, %, volume, value, ceiling, floor, open, high, low]}`
 * (whole VND). A tile's SIZE is the traded value (the site has no market-cap data, and the page says so), its COLOUR
 * the day's % change. The browser groups the tiles by industry and lays them out (js/market/treemap.js), so a visitor
 * can filter by exchange without another request.
 */
class MarketHeatmap
{
    /** Label for stocks whose industry is unknown (UPCoM names, funds, new listings). */
    public const OTHER = 'Khác';

    /**
     * The symbols that would be shown, busiest first. Used to look up names/industries for these only.
     *
     * @param  array<string, array<int, mixed>>  $quotes
     * @return string[]
     */
    public static function topSymbols(array $quotes, int $limit): array
    {
        $values = [];
        foreach ($quotes as $symbol => $q) {
            if (self::tradable($q)) {
                $values[(string) $symbol] = (float) $q[4];
            }
        }
        arsort($values);

        return array_slice(array_keys($values), 0, max(0, $limit));
    }

    /**
     * @param  array<string, array<int, mixed>>  $quotes
     * @param  array<string, array{name?: ?string, exchange?: ?string, industry?: ?string}>  $info
     * @return array{items: array<int, array<string, mixed>>, shown: int, total_value: int, advancers: int, decliners: int, flat: int}
     */
    public static function build(array $quotes, array $info, int $limit = 150): array
    {
        $items = [];
        foreach (self::topSymbols($quotes, $limit) as $symbol) {
            $q = $quotes[$symbol];
            $meta = $info[$symbol] ?? [];
            $industry = trim((string) ($meta['industry'] ?? ''));

            $items[] = [
                's' => $symbol,
                'n' => (string) ($meta['name'] ?? ''),
                // the exchange travels with the quote (the symbol list's own exchange column is not reliable); older snapshots have none
                'x' => is_string($q[10] ?? null) ? $q[10] : '',
                'i' => $industry !== '' ? $industry : self::OTHER,
                'p' => (int) $q[0],
                'c' => round((float) $q[2], 2),
                'v' => (int) $q[4],
                'q' => (int) ($q[3] ?? 0),
            ];
        }

        return [
            'items' => $items,
            'shown' => count($items),
            'total_value' => array_sum(array_column($items, 'v')),
            'advancers' => count(array_filter($items, fn (array $i) => $i['c'] > 0)),
            'decliners' => count(array_filter($items, fn (array $i) => $i['c'] < 0)),
            'flat' => count(array_filter($items, fn (array $i) => $i['c'] == 0)),
        ];
    }

    /** A quote that can be drawn: a price, a % change and some traded value (a stock that did not trade has no tile). */
    private static function tradable(mixed $q): bool
    {
        return is_array($q)
            && isset($q[0], $q[2], $q[4])
            && is_numeric($q[0]) && is_numeric($q[2]) && is_numeric($q[4])
            && (float) $q[4] > 0;
    }
}
