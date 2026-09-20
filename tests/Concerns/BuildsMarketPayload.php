<?php

namespace Tests\Concerns;

use App\Models\MarketSnapshot;

/**
 * A realistic output of py/get_market_overview.py, so tests never spawn Python (or touch the network) to get a
 * market snapshot. Prices are whole VND; `quotes` is {symbol: [price, reference, %, volume, value, ceiling, floor]}.
 */
trait BuildsMarketPayload
{
    /** @param array<string, mixed> $over top-level keys replace the defaults */
    protected function marketPayload(array $over = []): array
    {
        $row = fn (string $s, string $ex, int $price, int $chg, float $pct, int $vol, int $value) => [
            'symbol' => $s, 'exchange' => $ex, 'price' => $price, 'change' => $chg, 'percent' => $pct, 'volume' => $vol, 'value' => $value,
        ];

        $vic = $row('VIC', 'HOSE', 190500, 0, 0.0, 16_800_000, 3_197_000_000_000);
        $fpt = $row('FPT', 'HOSE', 71700, -2600, -3.5, 15_500_700, 1_129_620_000_000);
        $nvb = $row('NVB', 'HNX', 12800, 1100, 9.4, 2_200_000, 27_000_000_000);
        $vpl = $row('VPL', 'HOSE', 78400, -4150, -5.03, 3_900_000, 308_000_000_000);

        $board = fn (array $g, array $l, array $v) => ['gainers' => $g, 'losers' => $l, 'value' => $v];

        return array_replace([
            'fetched_at' => '2026-09-18T08:15:00Z',
            'trade_date' => '2026-09-18',
            'indices' => [
                ['code' => 'VNINDEX', 'name' => 'VN-Index', 'close' => 1815.66, 'change' => -7.11, 'percent' => -0.39, 'volume' => 881_177_232, 'date' => '2026-09-18',
                    'series' => [['2026-09-16', 1810.2, 500_000_000], ['2026-09-17', 1822.77, 601_599_155], ['2026-09-18', 1815.66, 881_177_232]]],
                ['code' => 'VN30', 'name' => 'VN30', 'close' => 1964.17, 'change' => -10.84, 'percent' => -0.55, 'volume' => 476_251_826, 'date' => '2026-09-18',
                    'series' => [['2026-09-17', 1975.01, 1], ['2026-09-18', 1964.17, 2]]],
                ['code' => 'HNXINDEX', 'name' => 'HNX-Index', 'close' => 275.29, 'change' => 1.39, 'percent' => 0.51, 'volume' => 42_546_336, 'date' => '2026-09-18',
                    'series' => [['2026-09-17', 273.9, 1], ['2026-09-18', 275.29, 2]]],
            ],
            'exchanges' => [
                'HOSE' => ['count' => 405, 'advancers' => 185, 'decliners' => 130, 'unchanged' => 90, 'ceiling' => 3, 'floor' => 4, 'value' => 22_000_000_000_000, 'volume' => 720_000_000],
                'HNX' => ['count' => 299, 'advancers' => 82, 'decliners' => 60, 'unchanged' => 157, 'ceiling' => 9, 'floor' => 4, 'value' => 575_000_000_000, 'volume' => 41_000_000],
                'UPCOM' => ['count' => 819, 'advancers' => 100, 'decliners' => 90, 'unchanged' => 20, 'ceiling' => 28, 'floor' => 21, 'value' => 329_000_000_000, 'volume' => 20_000_000],
            ],
            'movers' => [
                'ALL' => $board([$nvb], [$vpl, $fpt], [$vic, $fpt, $nvb]),
                'HOSE' => $board([], [$vpl, $fpt], [$vic, $fpt]),
                'HNX' => $board([$nvb], [], [$nvb]),
                'UPCOM' => $board([], [], []),
            ],
            'quotes' => [
                'VIC' => [190500, 190500, 0.0, 16_800_000, 3_197_000_000_000, 203800, 177200],
                'FPT' => [71700, 74300, -3.5, 15_500_700, 1_129_620_000_000, 79500, 69100],
                'NVB' => [12800, 11700, 9.4, 2_200_000, 27_000_000_000, 12900, 10500],
                'HPG' => [28500, 26650, 6.94, 30_000_000, 850_000_000_000, 28500, 24800],   // ceiling
            ],
            'errors' => [],
            'warnings' => [],
        ], $over);
    }

    /** Store a snapshot straight in the table (what MarketSnapshotRepository::save() would have written). */
    protected function seedMarketSnapshot(array $over = [], ?\DateTimeInterface $syncedAt = null): MarketSnapshot
    {
        $p = $this->marketPayload($over);
        $quotes = $p['quotes'];
        unset($p['quotes']);

        return MarketSnapshot::create([
            'trade_date' => $p['trade_date'], 'data' => $p, 'quotes' => $quotes,
            'fetched_at' => $p['fetched_at'], 'synced_at' => $syncedAt ?? now(),
        ]);
    }
}
