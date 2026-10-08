<?php

namespace Tests\Feature\Support;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * The parts of py/get_market_overview.py the home page relies on, run on a tiny hand-made price board (no network): every quote carries its
 * exchange as the 11th element (heat map filter), and foreign_flow() turns the board's foreign volumes into estimated values and top lists.
 * Skipped where Python/vnstock are not installed.
 */
class GetMarketOverviewScriptTest extends TestCase
{
    /** Run a snippet with the script importable as `m` and pandas as `pd`; returns the JSON object it prints on its last line. */
    private function python(string $body): array
    {
        $python = (string) config('services.python.path', 'python');
        $code = "import json, sys\nsys.path.insert(0, 'py')\nimport pandas as pd\nimport get_market_overview as m\n".$body;

        $out = [];
        $status = 0;
        exec('cd '.escapeshellarg(base_path()).' && '.escapeshellarg($python).' -c '.escapeshellarg($code).' 2>&1', $out, $status);
        $text = implode("\n", $out);

        if ($status !== 0 && (str_contains($text, 'ModuleNotFoundError') || str_contains($text, 'not found') || str_contains($text, 'No module'))) {
            $this->markTestSkipped('Python or vnstock is not available here.');
        }
        $this->assertSame(0, $status, $text);
        $line = collect($out)->last(fn ($l) => str_starts_with(trim($l), '{'));

        return json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    }

    private function analyse(): array
    {
        return $this->python(<<<'PY'
board = pd.DataFrame([
    {'symbol': 'FPT', 'close_price': 71700, 'reference_price': 74300, 'percent_change': -3.5, 'volume_accumulated': 1000, 'total_value': 71700000, 'ceiling_price': 79500, 'floor_price': 69100, 'open_price': 74500, 'high_price': 74800, 'low_price': 71700},
    {'symbol': 'NVB', 'close_price': 12800, 'reference_price': 11700, 'percent_change': 9.4, 'volume_accumulated': 2000, 'total_value': 25600000, 'ceiling_price': 12900, 'floor_price': 10500, 'open_price': 11800, 'high_price': 12900, 'low_price': 11700},
    {'symbol': 'ZZZ', 'close_price': 5000, 'reference_price': 5000, 'percent_change': 0, 'volume_accumulated': 0, 'total_value': 0, 'ceiling_price': 5300, 'floor_price': 4700, 'open_price': None, 'high_price': None, 'low_price': None},
])
quotes, stats, movers = m.analyse(board, {'FPT': 'HOSE', 'NVB': 'HNX'})
print(json.dumps({'quotes': quotes}))
PY)['quotes'];
    }

    /** The foreign-flow result for a board with: strong buyer PLX, strong seller TCB, noise SMA, a HNX buyer, an UPCOM seller and three rows that must be ignored. */
    private function foreign(): array
    {
        return $this->python(<<<'PY'
def row(sym, close, ref, buy, sell, ex=None):
    return {'symbol': sym, 'exchange': ex, 'close_price': close, 'reference_price': ref, 'foreign_buy_volume': buy, 'foreign_sell_volume': sell}
board = pd.DataFrame([
    row('PLX', 37600, 37000, 2903800, 468500),     # +2,43 m shares net on HOSE
    row('TCB', 31900, 32000, 547600, 5309000),     # -4,76 m shares net on HOSE
    row('SMA', 10000, 10000, 1000, 500),           # net +500 shares: noise
    row('NVB', 12800, 12000, 100000, 0),           # HNX buyer, 1,28 billion
    row('UPC', 20000, 20000, 0, 100000),           # UPCOM seller, 2 billion
    row('UNT', 0, 15000, 200000, 0),               # did not trade today: valued at the reference price
    row('E1VFVN30', 30000, 30000, 999999, 0, 'HOSE'),   # an ETF: not a 3-letter stock
    row('XYZ', 10000, 10000, 5000000, 0),          # exchange unknown: left out
    row('NOP', None, None, 5000000, 0),            # no price at all: left out
])
exch = {'PLX': 'HOSE', 'TCB': 'HOSE', 'SMA': 'HOSE', 'NVB': 'HNX', 'UPC': 'UPCOM', 'UNT': 'HOSE', 'E1VFVN30': 'HOSE', 'NOP': 'HOSE'}
print(json.dumps(m.foreign_flow(board, exch)))
PY);
    }

    // ── exchange on every quote ─────────────────────────────────────────────

    #[Group('marketHeatmap')]
    public function test_every_quote_carries_its_exchange_as_the_eleventh_element(): void
    {
        $quotes = $this->analyse();

        $this->assertCount(11, $quotes['FPT']);
        $this->assertSame('HOSE', $quotes['FPT'][10]);
        $this->assertSame('HNX', $quotes['NVB'][10]);
        $this->assertSame([71700, 74300, -3.5, 1000, 71700000, 79500, 69100, 74500, 74800, 71700], array_slice($quotes['FPT'], 0, 10), 'the first ten elements are unchanged');
    }

    #[Group('marketHeatmap')]
    public function test_a_symbol_whose_exchange_is_unknown_gets_null_not_a_guess(): void
    {
        $quotes = $this->analyse();

        $this->assertNull($quotes['ZZZ'][10]);
    }

    // ── foreign flow ────────────────────────────────────────────────────────

    #[Group('foreignFlow')]
    public function test_foreign_values_are_volume_times_price_summed_per_stock_and_the_totals_add_up(): void
    {
        $f = $this->foreign();

        // buy: PLX 109.182.880.000 + TCB 17.468.440.000 + SMA 10.000.000 + NVB 1.280.000.000 + UNT (200.000 x 15.000 reference) 3.000.000.000
        $this->assertSame(109_182_880_000 + 17_468_440_000 + 10_000_000 + 1_280_000_000 + 3_000_000_000, $f['buy_value']);
        // sell: PLX 17.615.600.000 + TCB 169.357.100.000 + SMA 5.000.000 + UPC 2.000.000.000
        $this->assertSame(17_615_600_000 + 169_357_100_000 + 5_000_000 + 2_000_000_000, $f['sell_value']);
        $this->assertSame($f['buy_value'] - $f['sell_value'], $f['net_value']);
        $this->assertSame($f['net_value'], array_sum(array_column($f['by_exchange'], 'net')), 'the exchanges add up to the total');
    }

    #[Group('foreignFlow')]
    public function test_the_net_figure_per_exchange_is_split_by_the_listing_not_by_the_board(): void
    {
        $by = $this->foreign()['by_exchange'];

        $this->assertSame(['HOSE', 'HNX', 'UPCOM'], array_keys($by));
        $this->assertSame(1_280_000_000, $by['HNX']['net']);
        $this->assertSame(-2_000_000_000, $by['UPCOM']['net']);
        $this->assertSame(109_182_880_000 - 17_615_600_000 + 17_468_440_000 - 169_357_100_000 + 5_000_000 + 3_000_000_000, $by['HOSE']['net']);
    }

    #[Group('foreignFlow')]
    public function test_the_top_lists_hold_only_real_positions_biggest_first(): void
    {
        $f = $this->foreign();

        $this->assertSame(['PLX', 'UNT', 'NVB'], array_column($f['top_buy'], 'symbol'));
        $this->assertSame(['TCB', 'UPC'], array_column($f['top_sell'], 'symbol'));
        $this->assertSame(91_567_280_000, $f['top_buy'][0]['net_value']);
        $this->assertSame(-151_888_660_000, $f['top_sell'][0]['net_value']);
        $this->assertSame(2_435_300, $f['top_buy'][0]['net_volume']);
        $this->assertNotContains('SMA', array_merge(array_column($f['top_buy'], 'symbol'), array_column($f['top_sell'], 'symbol')), 'a net position under 1 billion is noise');
    }

    #[Group('foreignFlow')]
    public function test_etfs_unknown_exchanges_and_priceless_rows_are_ignored(): void
    {
        $symbols = array_merge(array_column($this->foreign()['top_buy'], 'symbol'), array_column($this->foreign()['top_sell'], 'symbol'));

        foreach (['E1VFVN30', 'XYZ', 'NOP'] as $ignored) {
            $this->assertNotContains($ignored, $symbols);
        }
    }

    #[Group('foreignFlow')]
    public function test_a_stock_that_did_not_trade_is_valued_at_its_reference_price(): void
    {
        $unt = collect($this->foreign()['top_buy'])->firstWhere('symbol', 'UNT');

        $this->assertSame(15_000, $unt['price']);
        $this->assertSame(3_000_000_000, $unt['net_value']);
    }

    #[Group('foreignFlow')]
    public function test_the_lists_are_capped_at_five(): void
    {
        $f = $this->python(<<<'PY'
board = pd.DataFrame([{'symbol': 'B%02d' % i, 'close_price': 10000, 'reference_price': 10000, 'foreign_buy_volume': 1000000 + i * 1000, 'foreign_sell_volume': 0} for i in range(12)])
print(json.dumps(m.foreign_flow(board, {'B%02d' % i: 'HOSE' for i in range(12)})))
PY);

        $this->assertCount(5, $f['top_buy']);
        $this->assertSame([], $f['top_sell']);
        $this->assertSame('B11', $f['top_buy'][0]['symbol']);
    }

    #[Group('foreignFlow')]
    public function test_a_board_without_any_foreign_trading_gives_zero_totals_and_empty_lists(): void
    {
        $f = $this->python(<<<'PY'
board = pd.DataFrame([{'symbol': 'AAA', 'close_price': 10000, 'reference_price': 10000, 'foreign_buy_volume': 0, 'foreign_sell_volume': 0}])
print(json.dumps(m.foreign_flow(board, {'AAA': 'HOSE'})))
PY);

        $this->assertSame([0, 0, 0], [$f['buy_value'], $f['sell_value'], $f['net_value']]);
        $this->assertSame([[], []], [$f['top_buy'], $f['top_sell']]);
    }
}
