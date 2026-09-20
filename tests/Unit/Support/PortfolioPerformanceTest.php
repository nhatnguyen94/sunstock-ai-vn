<?php

namespace Tests\Unit\Support;

use App\Support\PortfolioPerformance;
use App\Support\PriceUnit;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class PortfolioPerformanceTest extends TestCase
{
    #[Group('portfolioPrices')]
    public function test_price_unit_converts_between_feed_thousands_and_whole_vnd(): void
    {
        $this->assertSame(22050.0, PriceUnit::toVnd(22.05));
        $this->assertSame(72700.0, PriceUnit::toVnd('72.7'));
        $this->assertNull(PriceUnit::toVnd(null));
        $this->assertSame(85.0, PriceUnit::toFeed(85000));
        $this->assertNull(PriceUnit::toFeed(null));
    }

    #[Group('portfolioPrices')]
    public function test_series_values_holdings_at_daily_closes_converted_to_vnd(): void
    {
        $holdings = [['symbol' => 'ACB', 'quantity' => 100, 'buy_price' => 20000, 'buy_date' => '2026-01-02']];
        $closes = ['ACB' => ['2026-01-02' => 20.0, '2026-01-03' => 21.5, '2026-01-04' => 19.0]];

        $series = PortfolioPerformance::series($holdings, $closes);

        $this->assertSame(['2026-01-02', '2026-01-03', '2026-01-04'], array_column($series, 'date'));
        $this->assertSame([2000000.0, 2150000.0, 1900000.0], array_column($series, 'value'));
        $this->assertSame([2000000.0, 2000000.0, 2000000.0], array_column($series, 'invested'));
    }

    #[Group('portfolioPrices')]
    public function test_a_holding_only_counts_from_its_buy_date(): void
    {
        $holdings = [
            ['symbol' => 'AAA', 'quantity' => 10, 'buy_price' => 10000, 'buy_date' => '2026-01-02'],
            ['symbol' => 'BBB', 'quantity' => 10, 'buy_price' => 50000, 'buy_date' => '2026-01-04'],
        ];
        $closes = [
            'AAA' => ['2026-01-02' => 10.0, '2026-01-03' => 10.0, '2026-01-04' => 10.0],
            'BBB' => ['2026-01-03' => 48.0, '2026-01-04' => 50.0],
        ];

        $series = PortfolioPerformance::series($holdings, $closes);
        $byDate = array_column($series, null, 'date');

        // 01-03: BBB has a close but is not owned yet -> only AAA
        $this->assertSame(100000.0, $byDate['2026-01-03']['value']);
        $this->assertSame(100000.0, $byDate['2026-01-03']['invested']);
        // 01-04: both owned
        $this->assertSame(600000.0, $byDate['2026-01-04']['value']);
        $this->assertSame(600000.0, $byDate['2026-01-04']['invested']);
    }

    #[Group('portfolioPrices')]
    public function test_gaps_carry_the_last_close_forward_and_unpriced_symbols_fall_back_to_the_buy_price(): void
    {
        $holdings = [
            ['symbol' => 'AAA', 'quantity' => 10, 'buy_price' => 10000, 'buy_date' => '2026-01-02'],
            ['symbol' => 'NEW', 'quantity' => 5, 'buy_price' => 40000, 'buy_date' => '2026-01-02'],   // no history at all
        ];
        $closes = ['AAA' => ['2026-01-02' => 10.0, '2026-01-05' => 12.0]];

        $series = PortfolioPerformance::series($holdings, $closes);
        $byDate = array_column($series, null, 'date');

        $this->assertSame(100000.0 + 200000.0, $byDate['2026-01-02']['value']);
        $this->assertSame(120000.0 + 200000.0, $byDate['2026-01-05']['value']);   // NEW stays at its buy price
    }

    #[Group('portfolioPrices')]
    public function test_no_holdings_yield_no_series_and_long_histories_are_thinned_keeping_the_ends(): void
    {
        $this->assertSame([], PortfolioPerformance::series([], []));

        $points = array_map(fn ($i) => ['date' => sprintf('d%04d', $i), 'value' => $i, 'invested' => 0], range(0, 999));
        $thin = PortfolioPerformance::thin($points, 100);

        $this->assertCount(100, $thin);
        $this->assertSame('d0000', $thin[0]['date']);
        $this->assertSame('d0999', $thin[99]['date']);
    }
}
