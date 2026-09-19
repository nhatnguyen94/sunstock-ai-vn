<?php

namespace Tests\Unit\Support;

use App\Support\FundMetrics;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Pure maths (Carbon only, no facades) => plain Unit test.
 */
class FundMetricsTest extends TestCase
{
    /** Daily NAV, one point per day from $start for $days days, growing $step per day. */
    private function daily(string $start, int $days, float $base = 100.0, float $step = 0.5): array
    {
        $nav = [];
        $d = new \DateTimeImmutable($start);
        for ($i = 0; $i < $days; $i++) {
            $nav[] = [$d->modify("+{$i} days")->format('Y-m-d'), $base + $i * $step];
        }

        return $nav;
    }

    #[Group('fundCatalog')]
    public function test_slice_keeps_only_points_inside_the_window_ending_at_the_latest_nav(): void
    {
        $nav = $this->daily('2026-01-01', 200);   // last point: 2026-07-19
        $slice = FundMetrics::slice($nav, '1M');

        $this->assertSame('2026-07-19', end($slice)[0]);
        $this->assertSame('2026-06-19', $slice[0][0]);   // 30 days before the last point, inclusive
        $this->assertCount(31, $slice);
    }

    #[Group('fundCatalog')]
    public function test_slice_all_and_unknown_window_return_the_whole_series(): void
    {
        $nav = $this->daily('2026-01-01', 50);

        $this->assertCount(50, FundMetrics::slice($nav, 'ALL'));
        $this->assertCount(50, FundMetrics::slice($nav, 'bogus'));
        $this->assertSame([], FundMetrics::slice([], '1Y'));
    }

    #[Group('fundCatalog')]
    public function test_return_pct_is_first_to_last_nav_in_the_window(): void
    {
        $nav = [['2026-01-01', 100.0], ['2026-01-15', 105.0], ['2026-01-30', 110.0]];

        $stats = FundMetrics::windowStats($nav, 'ALL');

        $this->assertSame(10.0, $stats['return_pct']);
        $this->assertSame(3, $stats['points']);
    }

    #[Group('fundCatalog')]
    public function test_max_drawdown_is_the_worst_peak_to_trough_fall(): void
    {
        // 100 -> 120 (peak) -> 90 (-25% from peak) -> 110
        $nav = [['2026-01-01', 100.0], ['2026-01-02', 120.0], ['2026-01-03', 90.0], ['2026-01-04', 110.0]];

        $this->assertSame(-25.0, FundMetrics::windowStats($nav, 'ALL')['max_drawdown']);
    }

    #[Group('fundCatalog')]
    public function test_a_monotonically_rising_series_has_zero_drawdown(): void
    {
        $this->assertSame(0.0, FundMetrics::windowStats($this->daily('2026-01-01', 30), '1M')['max_drawdown']);
    }

    #[Group('fundCatalog')]
    public function test_volatility_is_zero_for_a_constant_growth_rate_and_positive_for_noisy_series(): void
    {
        // Same % every day => zero standard deviation of returns
        $steady = [];
        $v = 100.0;
        for ($i = 0; $i < 40; $i++) {
            $steady[] = [(new \DateTimeImmutable('2026-01-01'))->modify("+{$i} days")->format('Y-m-d'), $v];
            $v *= 1.001;
        }
        $this->assertSame(0.0, FundMetrics::windowStats($steady, '3M')['volatility']);

        $noisy = [];
        foreach ([100, 103, 99, 104, 98, 105] as $i => $val) {
            $noisy[] = [(new \DateTimeImmutable('2026-01-01'))->modify("+{$i} days")->format('Y-m-d'), (float) $val];
        }
        $this->assertGreaterThan(10.0, FundMetrics::windowStats($noisy, '3M')['volatility']);
    }

    #[Group('fundCatalog')]
    public function test_volatility_is_not_reported_for_the_all_window_because_sampling_is_uneven(): void
    {
        $stats = FundMetrics::windowStats($this->daily('2026-01-01', 60), 'ALL');

        $this->assertNull($stats['volatility']);
        $this->assertNotNull($stats['return_pct']);
    }

    #[Group('fundCatalog')]
    public function test_too_few_points_yield_null_stats_instead_of_a_division_error(): void
    {
        foreach ([[], [['2026-01-01', 100.0]]] as $nav) {
            $stats = FundMetrics::windowStats($nav, '1Y');
            $this->assertNull($stats['return_pct']);
            $this->assertNull($stats['max_drawdown']);
            $this->assertNull($stats['volatility']);
        }

        // 2 points: return/drawdown are defined, volatility needs >= 3
        $two = FundMetrics::windowStats([['2026-01-01', 100.0], ['2026-01-02', 101.0]], 'ALL');
        $this->assertSame(1.0, $two['return_pct']);
        $this->assertNull($two['volatility']);
    }

    #[Group('fundCatalog')]
    public function test_all_windows_returns_every_configured_window(): void
    {
        $all = FundMetrics::allWindows($this->daily('2026-01-01', 400));

        $this->assertSame(array_keys(FundMetrics::WINDOWS), array_keys($all));
        $this->assertGreaterThan($all['1M']['points'], $all['1Y']['points']);
    }
}
