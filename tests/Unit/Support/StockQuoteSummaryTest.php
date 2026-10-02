<?php

namespace Tests\Unit\Support;

use App\Support\StockQuoteSummary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class StockQuoteSummaryTest extends TestCase
{
    /** Midnight Vietnam time of $daysAgo days before 2026-10-02, as the price repository returns it (epoch ms). */
    private function ms(int $daysAgo): int
    {
        return (strtotime('2026-10-02 00:00:00 UTC') - $daysAgo * 86400) * 1000;
    }

    /** @return array<string, mixed> */
    private function row(int $daysAgo, float $close, ?float $open = null, ?float $high = null, ?float $low = null, float $volume = 1_000_000): array
    {
        return ['time' => $this->ms($daysAgo), 'open' => $open ?? $close, 'high' => $high ?? $close, 'low' => $low ?? $close, 'close' => $close, 'volume' => $volume];
    }

    #[Group('stockPageUx')]
    public function test_a_stock_is_shown_in_whole_vnd_not_in_the_feeds_thousands(): void
    {
        $s = StockQuoteSummary::build([$this->row(1, 62.7, 62.7, 63.2, 62.4), $this->row(0, 62.1, 62.7, 63.2, 62.1)], 'FPT');

        $this->assertSame(1000, $s['scale']);
        $this->assertFalse($s['is_index']);
        $this->assertSame('₫', $s['unit']);
        $this->assertSame(0, $s['decimals']);
        $this->assertEqualsWithDelta(62_100.0, $s['close'], 0.001);
        $this->assertEqualsWithDelta(62_700.0, $s['prev_close'], 0.001);
        $this->assertEqualsWithDelta(-600.0, $s['change'], 0.001);
        $this->assertEqualsWithDelta(-0.957, $s['change_pct'], 0.001);
        $this->assertSame('down', $s['direction']);
        $this->assertEqualsWithDelta(62_700.0, $s['open'], 0.001);
        $this->assertEqualsWithDelta(63_200.0, $s['high'], 0.001);
        $this->assertEqualsWithDelta(62_100.0, $s['low'], 0.001);
        $this->assertSame('2026-10-02', $s['date']);
    }

    #[Group('stockPageUx')]
    public function test_an_index_stays_in_points_with_two_decimals(): void
    {
        $s = StockQuoteSummary::build([$this->row(1, 1749.30), $this->row(0, 1737.71)], 'vnindex');

        $this->assertSame(1, $s['scale']);
        $this->assertTrue($s['is_index']);
        $this->assertSame('điểm', $s['unit']);
        $this->assertSame(2, $s['decimals']);
        $this->assertEqualsWithDelta(1737.71, $s['close'], 0.001);
        $this->assertEqualsWithDelta(-11.59, $s['change'], 0.001);
    }

    #[Group('stockPageUx')]
    #[DataProvider('everyIndexCode')]
    public function test_every_known_index_code_is_in_points(string $code): void
    {
        $this->assertSame(1, StockQuoteSummary::scaleFor($code));
    }

    /** @return array<string, array{string}> */
    public static function everyIndexCode(): array
    {
        return array_map(fn ($c) => [$c], ['VNINDEX' => 'VNINDEX', 'VN30' => 'VN30', 'HNXINDEX' => 'HNXINDEX', 'HNX30' => 'HNX30', 'UPCOMINDEX' => 'UPCOMINDEX', 'VN100' => 'VN100']);
    }

    #[Group('stockPageUx')]
    public function test_an_etf_is_scaled_like_a_stock(): void
    {
        $this->assertSame(1000, StockQuoteSummary::scaleFor('E1VFVN30'));
        $this->assertSame(1000, StockQuoteSummary::scaleFor('FUEVFVND'));
    }

    #[Group('stockPageUx')]
    public function test_direction_covers_up_down_flat_and_no_previous_session(): void
    {
        $this->assertSame('up', StockQuoteSummary::build([$this->row(1, 10), $this->row(0, 10.5)], 'AAA')['direction']);
        $this->assertSame('flat', StockQuoteSummary::build([$this->row(1, 10), $this->row(0, 10)], 'AAA')['direction']);

        $single = StockQuoteSummary::build([$this->row(0, 10)], 'AAA');
        $this->assertSame('flat', $single['direction']);
        $this->assertNull($single['change']);
        $this->assertNull($single['change_pct']);
        $this->assertNull($single['prev_close']);
    }

    #[Group('stockPageUx')]
    public function test_no_rows_or_no_positive_close_means_nothing_to_show(): void
    {
        $this->assertNull(StockQuoteSummary::build([], 'FPT'));
        $this->assertNull(StockQuoteSummary::build([['time' => 1, 'close' => 0], ['time' => 2, 'close' => null], ['time' => 3, 'close' => 'x']], 'FPT'));
    }

    #[Group('stockPageUx')]
    public function test_rows_with_a_missing_close_are_skipped_and_the_last_good_one_is_used(): void
    {
        $s = StockQuoteSummary::build([$this->row(2, 10), $this->row(1, 11), ['time' => $this->ms(0), 'close' => null]], 'AAA');

        $this->assertEqualsWithDelta(11_000.0, $s['close'], 0.001);
        $this->assertEqualsWithDelta(10_000.0, $s['prev_close'], 0.001);
    }

    #[Group('stockPageUx')]
    public function test_day_position_places_the_close_inside_the_sessions_range(): void
    {
        $mid = StockQuoteSummary::build([$this->row(1, 50), $this->row(0, 55, 50, 60, 50)], 'AAA');
        $this->assertEqualsWithDelta(50.0, $mid['day_position'], 0.001);

        $top = StockQuoteSummary::build([$this->row(1, 50), $this->row(0, 60, 50, 60, 50)], 'AAA');
        $this->assertEqualsWithDelta(100.0, $top['day_position'], 0.001);

        $flat = StockQuoteSummary::build([$this->row(1, 50), $this->row(0, 50, 50, 50, 50)], 'AAA');
        $this->assertNull($flat['day_position'], 'a session with no range has no position');
    }

    #[Group('stockPageUx')]
    public function test_the_year_range_uses_the_last_252_sessions_only(): void
    {
        $rows = [$this->row(400, 99.0, 99, 200.0, 1.0)];   // an old extreme that must be ignored
        for ($i = 300; $i >= 1; $i--) {
            $rows[] = $this->row($i, 50.0, 50, 60.0, 40.0);
        }
        $rows[] = $this->row(0, 55.0, 50, 70.0, 45.0);

        $s = StockQuoteSummary::build($rows, 'AAA');

        $this->assertEqualsWithDelta(70_000.0, $s['year_high'], 0.001);
        $this->assertEqualsWithDelta(40_000.0, $s['year_low'], 0.001);
        $this->assertEqualsWithDelta(50.0, $s['year_position'], 0.001);   // 55 in 40–70
    }

    #[Group('stockPageUx')]
    public function test_volume_is_compared_with_the_twenty_sessions_before_the_latest_not_with_itself(): void
    {
        $rows = [];
        for ($i = 25; $i >= 1; $i--) {
            $rows[] = $this->row($i, 10, volume: 1_000_000);
        }
        $rows[] = $this->row(0, 10, volume: 3_000_000);

        $s = StockQuoteSummary::build($rows, 'AAA');

        $this->assertEqualsWithDelta(3_000_000, $s['volume'], 0.001);
        $this->assertEqualsWithDelta(1_000_000, $s['avg_volume'], 0.001);
        $this->assertEqualsWithDelta(3.0, $s['volume_ratio'], 0.001);
    }

    #[Group('stockPageUx')]
    public function test_volume_ratio_is_null_without_history_or_with_zero_average_volume(): void
    {
        $this->assertNull(StockQuoteSummary::build([$this->row(0, 10)], 'AAA')['volume_ratio']);
        $this->assertNull(StockQuoteSummary::build([$this->row(1, 10, volume: 0), $this->row(0, 10, volume: 500)], 'AAA')['volume_ratio']);
    }

    #[Group('stockPageUx')]
    public function test_returns_compare_with_the_last_close_on_or_before_the_window_start(): void
    {
        $rows = [$this->row(400, 40), $this->row(200, 50), $this->row(100, 60), $this->row(40, 70), $this->row(31, 80), $this->row(5, 90), $this->row(0, 100)];

        $r = StockQuoteSummary::build($rows, 'AAA')['returns'];

        // window start = latest − N days; the base is the last stored close on or before it
        $this->assertEqualsWithDelta((100 / 80 - 1) * 100, $r['1T'], 0.001);   // 30 d  → the row 31 days ago
        $this->assertEqualsWithDelta((100 / 60 - 1) * 100, $r['3T'], 0.001);   // 91 d  → the row 100 days ago
        $this->assertEqualsWithDelta((100 / 50 - 1) * 100, $r['6T'], 0.001);   // 182 d → the row 200 days ago
        $this->assertEqualsWithDelta((100 / 40 - 1) * 100, $r['1N'], 0.001);   // 365 d → the row 400 days ago
    }

    #[Group('stockPageUx')]
    public function test_a_return_is_null_when_the_history_does_not_reach_back_far_enough(): void
    {
        $r = StockQuoteSummary::build([$this->row(10, 90), $this->row(0, 100)], 'AAA')['returns'];

        $this->assertSame(['1T' => null, '3T' => null, '6T' => null, '1N' => null], $r);
    }
}
