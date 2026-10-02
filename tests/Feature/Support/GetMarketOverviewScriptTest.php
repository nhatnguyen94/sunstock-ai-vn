<?php

namespace Tests\Feature\Support;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * The part of py/get_market_overview.py the heat map relies on: every quote carries its exchange as the 11th element.
 * Runs the script's own analyse() on a tiny hand-made price board (no network); skipped where Python/vnstock are not
 * installed.
 */
class GetMarketOverviewScriptTest extends TestCase
{
    private function analyse(): array
    {
        $python = (string) config('services.python.path', 'python');
        $code = <<<'PY'
import json, sys
sys.path.insert(0, 'py')
import pandas as pd
import get_market_overview as m
board = pd.DataFrame([
    {'symbol': 'FPT', 'close_price': 71700, 'reference_price': 74300, 'percent_change': -3.5, 'volume_accumulated': 1000, 'total_value': 71700000, 'ceiling_price': 79500, 'floor_price': 69100, 'open_price': 74500, 'high_price': 74800, 'low_price': 71700},
    {'symbol': 'NVB', 'close_price': 12800, 'reference_price': 11700, 'percent_change': 9.4, 'volume_accumulated': 2000, 'total_value': 25600000, 'ceiling_price': 12900, 'floor_price': 10500, 'open_price': 11800, 'high_price': 12900, 'low_price': 11700},
    {'symbol': 'ZZZ', 'close_price': 5000, 'reference_price': 5000, 'percent_change': 0, 'volume_accumulated': 0, 'total_value': 0, 'ceiling_price': 5300, 'floor_price': 4700, 'open_price': None, 'high_price': None, 'low_price': None},
])
quotes, stats, movers = m.analyse(board, {'FPT': 'HOSE', 'NVB': 'HNX'})
print(json.dumps({'quotes': quotes}))
PY;

        $out = [];
        $status = 0;
        exec('cd '.escapeshellarg(base_path()).' && '.escapeshellarg($python).' -c '.escapeshellarg($code).' 2>&1', $out, $status);
        $text = implode("\n", $out);

        if ($status !== 0 && (str_contains($text, 'ModuleNotFoundError') || str_contains($text, 'not found') || str_contains($text, 'No module'))) {
            $this->markTestSkipped('Python or vnstock is not available here.');
        }
        $this->assertSame(0, $status, $text);
        $line = collect($out)->last(fn ($l) => str_starts_with(trim($l), '{'));

        return json_decode($line, true, 512, JSON_THROW_ON_ERROR)['quotes'];
    }

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
}
