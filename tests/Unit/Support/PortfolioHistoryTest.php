<?php

namespace Tests\Unit\Support;

use App\Support\PortfolioHistory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class PortfolioHistoryTest extends TestCase
{
    private function tx(string $date, string $type, int $qty, int $price, int $fee = 0, string $symbol = 'AAA'): array
    {
        return ['date' => $date, 'type' => $type, 'symbol' => $symbol, 'quantity' => $qty, 'price' => $price, 'fee' => $fee];
    }

    #[Group('portfolioInsights')]
    public function test_a_partial_sale_lowers_the_value_and_the_cost_basis_and_records_the_cash_flows(): void
    {
        $closes = ['AAA' => ['2026-01-02' => 10.0, '2026-01-05' => 11.0, '2026-01-06' => 12.0, '2026-01-07' => 13.0]];

        $s = PortfolioHistory::series([
            $this->tx('2026-01-02', 'buy', 100, 10_000, 1_000),
            $this->tx('2026-01-06', 'sell', 40, 12_000, 500),
        ], $closes);

        $this->assertSame(['2026-01-02', '2026-01-05', '2026-01-06', '2026-01-07'], array_column($s, 'date'));
        // day 1: 100 shares, cost 1,000,000 + 1,000 fee; money put in = the same
        $this->assertSame([1_000_000.0, 1_001_000.0, 1_001_000.0], [$s[0]['value'], $s[0]['invested'], $s[0]['flow']]);
        $this->assertSame(1_100_000.0, $s[1]['value']);
        $this->assertSame(0.0, $s[1]['flow']);
        // the sale: 40 of 100 shares leave (60% of the cost stays) and 40*12,000 - 500 comes out
        $this->assertSame([720_000.0, 600_600.0, -479_500.0], [$s[2]['value'], $s[2]['invested'], $s[2]['flow']]);
        $this->assertSame(780_000.0, $s[3]['value']);
    }

    #[Group('portfolioInsights')]
    public function test_a_fully_closed_position_keeps_its_history_and_then_contributes_nothing(): void
    {
        $closes = ['AAA' => ['2026-01-02' => 10.0, '2026-01-05' => 11.0, '2026-01-06' => 12.0]];

        $s = PortfolioHistory::series([$this->tx('2026-01-02', 'buy', 10, 10_000), $this->tx('2026-01-05', 'sell', 10, 11_000)], $closes);

        $this->assertSame(100_000.0, $s[0]['value']);        // it was worth something before the sale
        $this->assertSame(0.0, $s[1]['value']);
        $this->assertSame(0.0, $s[1]['invested']);
        $this->assertSame(0.0, end($s)['value']);
    }

    #[Group('portfolioInsights')]
    public function test_the_value_uses_the_close_not_the_trade_price_once_a_close_exists(): void
    {
        $s = PortfolioHistory::series([$this->tx('2026-01-02', 'buy', 100, 10_000)], ['AAA' => ['2026-01-02' => 10.5]]);

        $this->assertSame(1_050_000.0, $s[0]['value']);   // bought at 10,000, closed at 10,500 the same day
        $this->assertSame(1_000_000.0, $s[0]['flow']);    // the cash that went in is what was paid
    }

    #[Group('portfolioInsights')]
    public function test_days_without_a_close_carry_the_last_known_price_forward(): void
    {
        $closes = ['AAA' => ['2026-01-02' => 10.0, '2026-01-06' => 12.0], 'BBB' => ['2026-01-02' => 20.0, '2026-01-05' => 21.0, '2026-01-06' => 22.0]];

        $s = PortfolioHistory::series([$this->tx('2026-01-02', 'buy', 10, 10_000), $this->tx('2026-01-02', 'buy', 10, 20_000, 0, 'BBB')], $closes);

        // 2026-01-05 has no AAA close: AAA stays at 10,000 while BBB moves to 21,000
        $this->assertSame(10 * 10_000.0 + 10 * 21_000.0, $s[1]['value']);
    }

    #[Group('portfolioInsights')]
    public function test_selling_more_than_held_never_creates_negative_holdings_and_closes_before_the_first_trade_are_ignored(): void
    {
        $closes = ['AAA' => ['2025-12-01' => 1.0, '2026-01-02' => 10.0, '2026-01-05' => 10.0]];

        $s = PortfolioHistory::series([$this->tx('2026-01-02', 'buy', 5, 10_000), $this->tx('2026-01-05', 'sell', 50, 10_000)], $closes);

        $this->assertSame('2026-01-02', $s[0]['date']);
        $this->assertSame(0.0, end($s)['value']);
        $this->assertGreaterThanOrEqual(0.0, end($s)['invested']);
    }

    #[Group('portfolioInsights')]
    public function test_no_transactions_give_no_series_and_the_chart_version_drops_the_flows(): void
    {
        $this->assertSame([], PortfolioHistory::series([], []));

        $chart = PortfolioHistory::forChart([['date' => '2026-01-02', 'value' => 1.0, 'invested' => 1.0, 'flow' => 1.0]]);
        $this->assertSame([['date' => '2026-01-02', 'value' => 1.0, 'invested' => 1.0]], $chart);
    }
}
