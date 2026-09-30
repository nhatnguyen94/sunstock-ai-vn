<?php

namespace Tests\Unit\Support;

use App\Support\PortfolioRisk;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class PortfolioRiskTest extends TestCase
{
    /** @return array<string, float> `$n` business-looking dates starting 2026-01-01 => alternating returns */
    private function returns(int $n, float $up = 0.02, float $down = -0.01): array
    {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[date('Y-m-d', strtotime('2026-01-01 +' . ($i + 1) . ' days'))] = $i % 2 === 0 ? $up : $down;
        }

        return $out;
    }

    #[Group('portfolioInsights')]
    public function test_daily_returns_take_the_days_cash_flow_out_so_buying_more_is_not_a_gain(): void
    {
        $series = [
            ['date' => '2026-01-02', 'value' => 1000.0, 'flow' => 1000.0],
            ['date' => '2026-01-05', 'value' => 2100.0, 'flow' => 1000.0],   // put in another 1,000, the first 1,000 grew 10%
            ['date' => '2026-01-06', 'value' => 1050.0, 'flow' => -1155.0],  // took 1,155 out; the rest lost nothing: 2100 -> 2205 -> 1050
        ];

        $r = PortfolioRisk::dailyReturns($series);

        $this->assertEqualsWithDelta(0.10, $r['2026-01-05'], 1e-9);
        $this->assertEqualsWithDelta(0.05, $r['2026-01-06'], 1e-9);
        $this->assertArrayNotHasKey('2026-01-02', $r);   // the first day has no previous value
    }

    #[Group('portfolioInsights')]
    public function test_stats_compound_the_returns_and_find_the_worst_fall_from_a_peak(): void
    {
        $s = PortfolioRisk::stats(['2026-01-02' => 0.10, '2026-01-05' => -0.20, '2026-01-06' => 0.05]);

        $this->assertSame(3, $s['days']);
        $this->assertSame(-7.6, $s['total_return']);            // 1.10 * 0.80 * 1.05 - 1
        $this->assertSame(-20.0, $s['max_drawdown']);           // peak 1.10 -> 0.88
        $this->assertSame(['date' => '2026-01-02', 'percent' => 10.0], $s['best_day']);
        $this->assertSame(['date' => '2026-01-05', 'percent' => -20.0], $s['worst_day']);
        $this->assertNull($s['volatility']);                    // three observations are not a volatility
    }

    #[Group('portfolioInsights')]
    public function test_volatility_appears_only_with_enough_observations_and_is_annualised(): void
    {
        $this->assertNull(PortfolioRisk::stats($this->returns(19))['volatility']);

        $flat = PortfolioRisk::stats($this->returns(40, 0.01, 0.01));
        $this->assertSame(0.0, $flat['volatility']);

        $v = PortfolioRisk::stats($this->returns(40))['volatility'];
        $this->assertGreaterThan(20.0, $v);                     // ~1.5% daily swings -> well over 20% a year
        $this->assertLessThan(40.0, $v);
    }

    #[Group('portfolioInsights')]
    public function test_no_returns_give_an_empty_result(): void
    {
        $s = PortfolioRisk::stats([]);

        $this->assertSame(0, $s['days']);
        $this->assertNull($s['total_return']);
        $this->assertNull($s['best_day']);
    }

    #[Group('portfolioInsights')]
    public function test_beta_is_two_for_a_portfolio_that_moves_twice_as_much_as_the_market(): void
    {
        $market = $this->returns(30, 0.01, -0.005);
        $portfolio = array_map(fn ($r) => $r * 2, $market);

        $this->assertSame(2.0, PortfolioRisk::beta($portfolio, $market));
        $this->assertSame(-1.0, PortfolioRisk::beta(array_map(fn ($r) => -$r, $market), $market));
    }

    #[Group('portfolioInsights')]
    public function test_beta_is_unknown_without_enough_common_days_or_a_moving_benchmark(): void
    {
        $market = $this->returns(30);

        $this->assertNull(PortfolioRisk::beta($this->returns(10), $market));                 // fewer than 20 common days
        $this->assertNull(PortfolioRisk::beta($market, $this->returns(30, 0.0, 0.0)));       // flat market: division by zero
        $this->assertNull(PortfolioRisk::beta($market, []));
    }

    #[Group('portfolioInsights')]
    public function test_price_returns_and_the_return_between_two_dates(): void
    {
        $closes = ['2026-01-02' => 100.0, '2026-01-05' => 110.0, '2026-01-07' => 99.0];

        $this->assertEqualsWithDelta(0.10, PortfolioRisk::priceReturns($closes)['2026-01-05'], 1e-9);
        // a Saturday start uses Friday's close; an end date after the last bar uses the last bar
        $this->assertSame(-1.0, PortfolioRisk::priceReturnBetween($closes, '2026-01-03', '2026-01-09'));
        $this->assertSame(10.0, PortfolioRisk::priceReturnBetween($closes, '2026-01-02', '2026-01-05'));
        // history starting after the requested start is "unknown", not a shorter period
        $this->assertNull(PortfolioRisk::priceReturnBetween($closes, '2025-12-01', '2026-01-05'));
        $this->assertNull(PortfolioRisk::priceReturnBetween([], '2026-01-02', '2026-01-05'));
    }

    #[Group('portfolioInsights')]
    public function test_the_same_flows_invested_in_a_benchmark_follow_its_prices(): void
    {
        $series = [
            ['date' => '2026-01-02', 'value' => 0.0, 'flow' => 1000.0],
            ['date' => '2026-01-05', 'value' => 0.0, 'flow' => 1000.0],
            ['date' => '2026-01-06', 'value' => 0.0, 'flow' => -500.0],
        ];
        $closes = ['2026-01-02' => 10.0, '2026-01-05' => 20.0, '2026-01-06' => 25.0];

        $sim = PortfolioRisk::simulate($series, $closes);

        // 100 units, +50 = 150, -20 = 130 units at 25
        $this->assertSame([1000.0, 3000.0, 3250.0], array_column($sim['series'], 'value'));
        $this->assertSame(3250.0, $sim['final_value']);
        $this->assertTrue($sim['covered']);
    }

    #[Group('portfolioInsights')]
    public function test_the_simulation_says_when_the_benchmark_history_starts_too_late_and_never_sells_units_it_does_not_have(): void
    {
        $series = [['date' => '2026-01-02', 'value' => 0.0, 'flow' => -500.0], ['date' => '2026-03-02', 'value' => 0.0, 'flow' => 100.0]];

        $sim = PortfolioRisk::simulate($series, ['2026-02-01' => 10.0, '2026-03-02' => 10.0]);

        $this->assertFalse($sim['covered']);
        $this->assertSame(0.0, $sim['series'][0]['value']);   // a sale before any purchase cannot go negative
        $this->assertSame(['series' => [], 'covered' => false, 'final_value' => null], PortfolioRisk::simulate($series, []));
    }
}
