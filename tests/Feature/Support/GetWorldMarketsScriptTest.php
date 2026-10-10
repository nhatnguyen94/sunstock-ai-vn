<?php

namespace Tests\Feature\Support;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * py/get_world_markets.py with vnstock's Quote replaced by a stand-in (no network, no vnstock import): the arithmetic, the cleaning of the
 * bars, and the rule that one failing market must not lose the others.
 */
class GetWorldMarketsScriptTest extends TestCase
{
    /** Run `main()` of the script against fake bars; `$bars` maps a code to a list of [date, close] (or null to make that market fail). */
    private function runWorld(array $bars, string $extra = ''): array
    {
        $python = (string) config('services.python.path', 'python');
        $json = json_encode($bars);
        $code = <<<PY
import json, sys, types
import pandas as pd
bars = json.loads('{$this->escape($json)}')
class Quote:
    def __init__(self, source=None, symbol=None):
        self.symbol = symbol
    def history(self, start, end, interval):
        rows = bars[self.symbol]
        if rows is None:
            raise RuntimeError('source down')
        return pd.DataFrame([{'time': pd.Timestamp(d), 'close': c} for d, c in rows])
for name in ('vnstock', 'vnstock.api', 'vnstock.api.quote'):
    sys.modules[name] = types.ModuleType(name)
sys.modules['vnstock.api.quote'].Quote = Quote
sys.path.insert(0, 'py')
import get_world_markets as w
w.MARKETS = [(c, c + ' name', 'Region') for c in bars]
{$extra}
w.main()
PY;

        $out = [];
        $status = 0;
        exec('cd '.escapeshellarg(base_path()).' && '.escapeshellarg($python).' -c '.escapeshellarg($code).' 2>&1', $out, $status);
        $text = implode("\n", $out);

        if ($status !== 0 && (str_contains($text, 'ModuleNotFoundError') || str_contains($text, 'not found'))) {
            $this->markTestSkipped('Python (or pandas) is not available here.');
        }
        $this->assertSame(0, $status, $text);

        return json_decode((string) collect($out)->last(fn ($l) => str_starts_with(trim($l), '{')), true, 512, JSON_THROW_ON_ERROR);
    }

    private function escape(string $json): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $json);
    }

    #[Group('worldMarkets')]
    public function test_the_last_two_closes_give_the_change_and_the_series_keeps_the_latest_eight(): void
    {
        $dates = ['2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04', '2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08', '2026-10-09', '2026-10-10'];
        $closes = [100.0, 101.0, 102.0, 103.0, 104.0, 105.0, 106.0, 107.0, 110.0, 99.0];

        $result = $this->runWorld(['INX' => array_map(null, $dates, $closes)]);
        $m = $result['markets'][0];

        $this->assertSame('INX', $m['code']);
        $this->assertSame('INX name', $m['name']);
        $this->assertSame(99.0, $m['close']);
        $this->assertSame(110.0, $m['previous']);
        $this->assertSame(-11.0, $m['change']);
        $this->assertSame(-10.0, $m['percent']);
        $this->assertSame('2026-10-10', $m['date']);
        $this->assertSame(8, count($m['series']));
        $this->assertSame([102.0, 103.0, 104.0, 105.0, 106.0, 107.0, 110.0, 99.0], $m['series'], 'oldest first, latest bar last');
        $this->assertSame([], $result['errors']);
    }

    #[Group('worldMarkets')]
    public function test_one_failing_market_is_reported_and_the_others_are_kept(): void
    {
        $result = $this->runWorld([
            'INX' => [['2026-10-07', 100.0], ['2026-10-08', 101.0]],
            'BAD' => null,
            'ONE' => [['2026-10-08', 5.0]],
        ]);

        $this->assertSame(['INX'], array_column($result['markets'], 'code'));
        $this->assertSame(['BAD', 'ONE'], array_keys($result['errors']));
        $this->assertStringContainsString('source down', $result['errors']['BAD']);
        $this->assertStringContainsString('fewer than two', $result['errors']['ONE']);
    }

    #[Group('worldMarkets')]
    public function test_when_nothing_answers_the_script_says_so_instead_of_printing_an_empty_list(): void
    {
        $result = $this->runWorld(['A' => null, 'B' => null]);

        $this->assertSame('No world market answered', $result['error']);
        $this->assertArrayNotHasKey('markets', $result);
    }

    #[Group('worldMarkets')]
    public function test_bars_with_a_missing_close_are_skipped(): void
    {
        $result = $this->runWorld(['INX' => [['2026-10-06', 100.0], ['2026-10-07', null], ['2026-10-08', 102.0]]]);

        $this->assertSame(100.0, $result['markets'][0]['previous'], 'the empty bar is ignored, so the previous close is the one before it');
        $this->assertSame(2.0, $result['markets'][0]['percent']);
    }

    #[Group('worldMarkets')]
    public function test_gold_silver_and_the_dong_rate_carry_a_unit_and_decimals_but_indices_do_not(): void
    {
        $bars = [['2026-10-08', 4133.93], ['2026-10-09', 4193.83]];

        $result = $this->runWorld(['INX' => $bars, 'XAUUSD' => $bars, 'USDVND' => [['2026-10-08', 25889.0], ['2026-10-09', 25879.0]]]);
        $byCode = array_column($result['markets'], null, 'code');

        $this->assertSame('USD/oz', $byCode['XAUUSD']['unit']);
        $this->assertSame(2, $byCode['XAUUSD']['decimals']);
        $this->assertSame('₫', $byCode['USDVND']['unit']);
        $this->assertSame(0, $byCode['USDVND']['decimals']);
        $this->assertSame(-10.0, $byCode['USDVND']['change']);
        $this->assertArrayNotHasKey('unit', $byCode['INX']);
        $this->assertArrayNotHasKey('decimals', $byCode['INX']);
    }

    #[Group('worldMarkets')]
    public function test_only_codes_that_were_checked_against_the_vnstock_msn_maps_are_listed(): void
    {
        $source = file_get_contents(base_path('py/get_world_markets.py'));

        foreach (['XAUUSD', 'XAGUSD', 'USDVND'] as $code) {
            $this->assertStringContainsString("('{$code}',", $source);
        }
        // guessed codes answered something that is not the instrument (BZ 15.73, GC 5.3, SI 0.07) and Bitcoin returned no bars
        foreach (['BZ', 'GC', 'SI', 'CL', 'BTC', 'ETH'] as $code) {
            $this->assertStringNotContainsString("('{$code}',", $source);
        }
    }

    #[Group('worldMarkets')]
    public function test_the_script_registers_the_eight_markets_the_strip_is_built_for(): void
    {
        $source = file_get_contents(base_path('py/get_world_markets.py'));

        foreach (['INX', 'COMP', 'DJI', 'UKX', 'DAX', 'N225', 'HSI', '000001'] as $code) {
            $this->assertStringContainsString("('{$code}',", $source);
        }
        $this->assertStringContainsString("source='MSN'", $source);
    }
}
