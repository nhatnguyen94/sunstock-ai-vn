<?php

namespace Tests\Unit\Support;

use App\Support\EtfMetrics;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class EtfMetricsTest extends TestCase
{
    /** One bar per calendar day ending on `$end`, close rising by `$step` per day from `$start`. */
    private function bars(string $end, int $days, float $start = 10.0, float $step = 0.01, int $volume = 1000): array
    {
        $rows = [];
        $last = Carbon::parse($end);
        for ($i = $days - 1; $i >= 0; $i--) {
            $rows[] = [$last->copy()->subDays($i)->toDateString(), $start + ($days - 1 - $i) * $step, $volume];
        }

        return $rows;
    }

    #[Group('etf')]
    public function test_a_window_the_history_does_not_cover_is_reported_as_missing_not_shortened(): void
    {
        $eightMonths = $this->bars('2026-09-30', 240);

        $this->assertNull(EtfMetrics::stats($eightMonths, '3Y')['return_pct']);
        $this->assertNull(EtfMetrics::stats($eightMonths, '1Y')['return_pct']);
        $this->assertNotNull(EtfMetrics::stats($eightMonths, '6M')['return_pct']);
    }

    #[Group('etf')]
    public function test_a_covered_window_returns_the_price_change_drawdown_and_volatility(): void
    {
        $rows = $this->bars('2026-09-30', 400, 10.0, 0.0);
        // last 30 days: 10 -> 12 -> 9 (a peak-to-trough fall of 25%)
        $rows[count($rows) - 30][1] = 10.0;
        $rows[count($rows) - 15][1] = 12.0;
        $rows[count($rows) - 1][1] = 9.0;

        $s = EtfMetrics::stats($rows, '1M');

        $this->assertSame(-10.0, $s['return_pct']);
        $this->assertSame(-25.0, $s['max_drawdown']);
        $this->assertNotNull($s['volatility']);
    }

    #[Group('etf')]
    public function test_no_rows_give_all_nulls(): void
    {
        $this->assertSame(['return_pct' => null, 'max_drawdown' => null, 'volatility' => null, 'points' => 0], EtfMetrics::stats([], '1M'));
        $this->assertNull(EtfMetrics::ytd([]));
        $this->assertNull(EtfMetrics::averageValue([]));
        $this->assertSame(['low' => null, 'high' => null], EtfMetrics::range52w([]));
    }

    #[Group('etf')]
    public function test_ytd_is_measured_from_the_last_close_of_the_previous_year(): void
    {
        $rows = [
            ['2025-12-30', 20.0, 1], ['2025-12-31', 25.0, 1],
            ['2026-01-02', 26.0, 1], ['2026-09-30', 30.0, 1],
        ];

        $this->assertSame(20.0, EtfMetrics::ytd($rows));   // 25 -> 30
    }

    #[Group('etf')]
    public function test_ytd_is_null_for_a_fund_listed_this_year(): void
    {
        $this->assertNull(EtfMetrics::ytd([['2026-03-02', 10.0, 1], ['2026-09-30', 12.0, 1]]));
    }

    #[Group('etf')]
    public function test_average_value_uses_only_the_last_sessions_and_skips_days_without_trading(): void
    {
        $rows = [
            ['2026-09-24', 10.0, 999_999],   // outside the 3-session window
            ['2026-09-25', 10.0, 100],       // 10 000 ₫ * 100 = 1,000,000
            ['2026-09-28', 10.0, 0],         // no trading: not part of the average
            ['2026-09-29', 20.0, 100],       // 2,000,000
        ];

        $this->assertSame(1_500_000, EtfMetrics::averageValue($rows, 3));
        $this->assertNull(EtfMetrics::averageValue([['2026-09-29', 10.0, 0]]));
    }

    #[Group('etf')]
    public function test_the_52_week_range_ignores_older_prices_and_converts_to_whole_vnd(): void
    {
        $rows = [['2025-01-02', 5.0, 1], ['2025-10-01', 12.5, 1], ['2026-03-01', 9.0, 1], ['2026-09-30', 11.0, 1]];

        $this->assertSame(['low' => 9000, 'high' => 12500], EtfMetrics::range52w($rows));
    }
}
