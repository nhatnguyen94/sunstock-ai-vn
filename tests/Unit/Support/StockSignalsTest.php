<?php

namespace Tests\Unit\Support;

use App\Support\StockSignals;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class StockSignalsTest extends TestCase
{
    private const TODAY = '2026-10-08';

    /** A quote as the snapshot stores it: [price, ref, %, volume, value, ...] in whole VND. */
    private function quote(int $price, float $pct, int $volume, int $value): array
    {
        return [$price, $price, $pct, $volume, $value];
    }

    /** History window for one symbol in FEED units (thousands): 200 sessions, last bar 3 days ago. */
    private function history(float $high, float $low, int $n = 200, string $last = '2026-10-05'): array
    {
        return ['n' => $n, 'high' => $high, 'low' => $low, 'last_date' => $last];
    }

    /** @return array<int, array{0: string, 1: float, 2: float}> */
    private function bars(array $closes, float $volume = 1_000_000): array
    {
        $out = [];
        foreach ($closes as $i => $c) {
            $out[] = [date('Y-m-d', strtotime(self::TODAY.' -'.(count($closes) - $i).' days')), $c, $volume];
        }

        return $out;
    }

    // ── RSI ─────────────────────────────────────────────────────────────────

    #[Group('stockSignals')]
    public function test_rsi_is_100_for_a_steady_rise_0_for_a_steady_fall_and_50_when_flat(): void
    {
        $this->assertSame(100.0, StockSignals::rsi(range(10, 30)));
        $this->assertSame(0.0, StockSignals::rsi(range(30, 10)));
        $this->assertSame(50.0, StockSignals::rsi(array_fill(0, 20, 12)));
    }

    #[Group('stockSignals')]
    public function test_rsi_matches_a_hand_worked_example(): void
    {
        // 14 changes: 8 gains of +1 and 6 losses of -1 -> avg gain 8/14, avg loss 6/14 -> RS 4/3 -> RSI 57,14
        $closes = [10, 11, 10, 11, 10, 11, 10, 11, 10, 11, 12, 13, 12, 11, 12];

        $this->assertEqualsWithDelta(57.14, StockSignals::rsi($closes), 0.01);
    }

    #[Group('stockSignals')]
    public function test_rsi_needs_period_plus_one_closes(): void
    {
        $this->assertNull(StockSignals::rsi(range(1, 14)));
        $this->assertNotNull(StockSignals::rsi(range(1, 15)));
        $this->assertNull(StockSignals::rsi([]));
    }

    #[Group('stockSignals')]
    public function test_rsi_uses_wilder_smoothing_so_old_moves_fade(): void
    {
        $recoveredFromSlump = array_merge(range(40, 20), range(21, 40));   // long fall, then a long rise

        $this->assertGreaterThan(StockSignals::rsi(range(30, 10)), StockSignals::rsi($recoveredFromSlump));
        $this->assertLessThan(100.0, StockSignals::rsi($recoveredFromSlump));
    }

    // ── breakouts / breakdowns ──────────────────────────────────────────────

    #[Group('stockSignals')]
    public function test_a_price_above_the_window_high_is_a_breakout_with_the_distance_measured(): void
    {
        $r = StockSignals::evaluate(['AAA' => $this->quote(52_000, 3.0, 1, 10_000_000_000)], ['AAA' => $this->history(50.0, 30.0)], [], self::TODAY);

        $this->assertSame('AAA', $r['breakout'][0]['s']);
        $this->assertSame(50_000, $r['breakout'][0]['ref'], 'feed units are converted to whole VND');
        $this->assertSame(4.0, $r['breakout'][0]['over']);
        $this->assertSame([], $r['breakdown']);
        $this->assertSame(1, $r['breakout_total']);
    }

    #[Group('stockSignals')]
    public function test_a_price_below_the_window_low_is_a_breakdown(): void
    {
        $r = StockSignals::evaluate(['AAA' => $this->quote(28_500, -2.0, 1, 10_000_000_000)], ['AAA' => $this->history(50.0, 30.0)], [], self::TODAY);

        $this->assertSame('AAA', $r['breakdown'][0]['s']);
        $this->assertSame(30_000, $r['breakdown'][0]['ref']);
        $this->assertSame(5.26, $r['breakdown'][0]['under']);
        $this->assertSame([], $r['breakout']);
    }

    #[Group('stockSignals')]
    public function test_a_price_inside_the_window_is_no_signal_and_a_rise_that_is_still_below_the_old_high_is_not_a_breakout(): void
    {
        $r = StockSignals::evaluate(['AAA' => $this->quote(45_000, 4.0, 1, 10_000_000_000)], ['AAA' => $this->history(50.0, 30.0)], [], self::TODAY);

        $this->assertSame([], $r['breakout']);
        $this->assertSame([], $r['breakdown']);
        $this->assertSame(1, $r['universe']);
    }

    #[Group('stockSignals')]
    public function test_a_breakout_needs_the_move_to_be_up_and_a_breakdown_down(): void
    {
        $r = StockSignals::evaluate([
            'UP1' => $this->quote(52_000, -0.5, 1, 10_000_000_000),   // above the high but down on the day (gap-up that faded)
            'DN1' => $this->quote(28_000, 0.4, 1, 10_000_000_000),
        ], ['UP1' => $this->history(50.0, 30.0), 'DN1' => $this->history(50.0, 30.0)], [], self::TODAY);

        $this->assertSame([], $r['breakout']);
        $this->assertSame([], $r['breakdown']);
    }

    // ── eligibility ─────────────────────────────────────────────────────────

    #[Group('stockSignals')]
    public function test_thin_stale_or_short_histories_and_non_stocks_are_never_considered(): void
    {
        $price = $this->quote(52_000, 3.0, 1, 10_000_000_000);
        $hist = $this->history(50.0, 30.0);
        $r = StockSignals::evaluate([
            'OK1' => $price,
            'THN' => $this->quote(52_000, 3.0, 1, 2_000_000_000),            // under 3 billion traded
            'STL' => $price,                                                   // last stored bar 30 days old
            'SHT' => $price,                                                   // only 50 sessions of history
            'NOH' => $price,                                                   // no history at all
            'E1VFVN30' => $price,                                              // an ETF, not a 3-letter stock
            'CFPT2301' => $price,                                              // a covered warrant
            'BAD' => [52_000],                                                 // broken quote
        ], ['OK1' => $hist, 'THN' => $hist, 'STL' => $this->history(50.0, 30.0, 200, '2026-09-01'), 'SHT' => $this->history(50.0, 30.0, 50), 'E1VFVN30' => $hist, 'CFPT2301' => $hist, 'BAD' => $hist], [], self::TODAY);

        $this->assertSame(1, $r['universe']);
        $this->assertSame(['OK1'], array_column($r['breakout'], 's'));
    }

    // ── volume ──────────────────────────────────────────────────────────────

    #[Group('stockSignals')]
    public function test_volume_at_twice_the_recent_average_with_real_money_is_a_spike(): void
    {
        $bars = ['AAA' => $this->bars(array_fill(0, 20, 40.0), 1_000_000)];
        $r = StockSignals::evaluate(['AAA' => $this->quote(40_000, 1.0, 2_500_000, 100_000_000_000)], ['AAA' => $this->history(60.0, 30.0)], $bars, self::TODAY);

        $this->assertSame('AAA', $r['volume'][0]['s']);
        $this->assertSame(2.5, $r['volume'][0]['ratio']);
    }

    #[Group('stockSignals')]
    public function test_a_spike_needs_enough_bars_enough_money_and_a_real_doubling(): void
    {
        $hist = ['AAA' => $this->history(60.0, 30.0), 'BBB' => $this->history(60.0, 30.0), 'CCC' => $this->history(60.0, 30.0), 'DDD' => $this->history(60.0, 30.0)];
        $r = StockSignals::evaluate([
            'AAA' => $this->quote(40_000, 1.0, 1_900_000, 100_000_000_000),   // 1,9x: not enough
            'BBB' => $this->quote(40_000, 1.0, 5_000_000, 4_000_000_000),     // 5x but under 5 billion
            'CCC' => $this->quote(40_000, 1.0, 5_000_000, 100_000_000_000),   // only 5 bars to average
            'DDD' => $this->quote(40_000, 1.0, 5_000_000, 100_000_000_000),   // zero average volume
        ], $hist, [
            'AAA' => $this->bars(array_fill(0, 20, 40.0), 1_000_000),
            'BBB' => $this->bars(array_fill(0, 20, 40.0), 1_000_000),
            'CCC' => $this->bars(array_fill(0, 5, 40.0), 1_000_000),
            'DDD' => $this->bars(array_fill(0, 20, 40.0), 0),
        ], self::TODAY);

        $this->assertSame([], $r['volume']);
    }

    #[Group('stockSignals')]
    public function test_only_the_last_20_sessions_set_the_volume_average(): void
    {
        $old = $this->bars(array_fill(0, 30, 40.0), 10_000_000);    // heavy old trading
        $recent = array_slice($old, 0, 10);
        foreach (array_slice($this->bars(array_fill(0, 20, 40.0), 1_000_000), 0, 20) as $b) {
            $recent[] = $b;
        }
        $r = StockSignals::evaluate(['AAA' => $this->quote(40_000, 1.0, 3_000_000, 100_000_000_000)], ['AAA' => $this->history(60.0, 30.0)], ['AAA' => $recent], self::TODAY);

        $this->assertSame(3.0, $r['volume'][0]['ratio'], 'the 10 heavy old bars are outside the 20-session average');
    }

    // ── RSI signals ─────────────────────────────────────────────────────────

    #[Group('stockSignals')]
    public function test_a_long_run_up_is_overbought_and_a_long_slide_oversold(): void
    {
        $r = StockSignals::evaluate([
            'UPP' => $this->quote(45_000, 2.0, 1, 10_000_000_000),
            'DWN' => $this->quote(15_000, -2.0, 1, 10_000_000_000),
        ], ['UPP' => $this->history(60.0, 10.0), 'DWN' => $this->history(60.0, 10.0)], [
            'UPP' => $this->bars(range(30, 44)),
            'DWN' => $this->bars(range(30, 16)),
        ], self::TODAY);

        $this->assertSame(['UPP'], array_column($r['overbought'], 's'));
        $this->assertGreaterThanOrEqual(70, $r['overbought'][0]['rsi']);
        $this->assertSame(['DWN'], array_column($r['oversold'], 's'));
        $this->assertLessThanOrEqual(30, $r['oversold'][0]['rsi']);
    }

    #[Group('stockSignals')]
    public function test_todays_price_is_the_last_point_of_the_rsi(): void
    {
        $flat = $this->bars(array_fill(0, 15, 30.0));   // flat history, so the RSI is decided by today's jump alone
        $r = StockSignals::evaluate(['AAA' => $this->quote(36_000, 20.0, 1, 10_000_000_000)], ['AAA' => $this->history(60.0, 10.0)], ['AAA' => $flat], self::TODAY);

        $this->assertSame(100.0, $r['overbought'][0]['rsi']);
    }

    #[Group('stockSignals')]
    public function test_too_few_closes_means_no_rsi_signal(): void
    {
        $r = StockSignals::evaluate(['AAA' => $this->quote(45_000, 2.0, 1, 10_000_000_000)], ['AAA' => $this->history(60.0, 10.0)], ['AAA' => $this->bars(range(30, 38))], self::TODAY);

        $this->assertSame([], $r['overbought']);
    }

    // ── ordering and limits ─────────────────────────────────────────────────

    #[Group('stockSignals')]
    public function test_lists_are_ranked_by_how_extreme_each_signal_is_and_capped_with_the_total_kept(): void
    {
        $quotes = $history = [];
        foreach (range(1, 9) as $i) {
            $sym = 'S'.$i.'X';
            $quotes[$sym] = $this->quote(50_000 + $i * 500, 2.0, 1, 10_000_000_000);   // i x 1 % above a 50 high
            $history[$sym] = $this->history(50.0, 30.0);
        }

        $r = StockSignals::evaluate($quotes, $history, [], self::TODAY);

        $this->assertCount(StockSignals::LIST_LIMIT, $r['breakout']);
        $this->assertSame(9, $r['breakout_total']);
        $this->assertSame('S9X', $r['breakout'][0]['s'], 'the biggest breakout first');
        $this->assertSame(['over'], array_values(array_unique(array_map(fn ($k) => $k === 'over' ? 'over' : null, array_filter(array_keys($r['breakout'][0]), fn ($k) => $k === 'over')))));
    }

    #[Group('stockSignals')]
    public function test_every_list_has_a_total_and_an_empty_market_gives_empty_lists(): void
    {
        $r = StockSignals::evaluate([], [], [], self::TODAY);

        foreach (['breakout', 'breakdown', 'volume', 'overbought', 'oversold'] as $key) {
            $this->assertSame([], $r[$key]);
            $this->assertSame(0, $r[$key.'_total']);
        }
        $this->assertSame(0, $r['universe']);
    }

    #[Group('stockSignals')]
    public function test_all_digit_symbols_arriving_as_integer_array_keys_are_ignored_without_errors(): void
    {
        $r = StockSignals::evaluate([123 => $this->quote(52_000, 3.0, 1, 10_000_000_000)], ['123' => $this->history(50.0, 30.0)], [], self::TODAY);

        $this->assertSame(0, $r['universe']);
        $this->assertSame([], $r['breakout']);
    }
}
