<?php

namespace Tests\Unit\Models;

use App\Models\Portfolio;
use App\Models\PortfolioItem;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests: no Laravel app booted, no database touched. Portfolio's
 * calculate*() methods read the `items` relation *property* (cacheable via
 * setRelation), not items() (which would force a query) — see the docblock
 * on Portfolio::recalculateTotals() for why that distinction matters in
 * production (a stale cached relation caused a real bug during this
 * session's Portfolio audit).
 */
class PortfolioTest extends TestCase
{
    private function portfolioWithItems(array $itemSpecs): Portfolio
    {
        $portfolio = new Portfolio(['user_id' => 1, 'name' => 'Test Portfolio']);
        $portfolio->id = 1;
        $portfolio->setRelation('items', collect($itemSpecs)->map(function (array $spec) {
            return new PortfolioItem([
                'stock_symbol' => $spec['symbol'] ?? 'TEST',
                'quantity' => $spec['quantity'],
                'buy_price' => $spec['buy_price'],
                'current_price' => $spec['current_price'],
            ]);
        }));

        return $portfolio;
    }

    #[Group('portfolioTotals')]
    public function test_calculate_current_value_sums_quantity_times_current_price(): void
    {
        $portfolio = $this->portfolioWithItems([
            ['quantity' => 10, 'buy_price' => 20, 'current_price' => 25],
            ['quantity' => 5, 'buy_price' => 100, 'current_price' => 90],
        ]);

        // 10*25 + 5*90 = 250 + 450 = 700
        $this->assertEqualsWithDelta(700.0, $portfolio->calculateCurrentValue(), 0.001);
    }

    #[Group('portfolioTotals')]
    public function test_calculate_total_invested_sums_quantity_times_buy_price(): void
    {
        $portfolio = $this->portfolioWithItems([
            ['quantity' => 10, 'buy_price' => 20, 'current_price' => 25],
            ['quantity' => 5, 'buy_price' => 100, 'current_price' => 90],
        ]);

        // 10*20 + 5*100 = 200 + 500 = 700
        $this->assertEqualsWithDelta(700.0, $portfolio->calculateTotalInvested(), 0.001);
    }

    #[Group('portfolioTotals')]
    public function test_calculations_are_zero_for_an_empty_portfolio(): void
    {
        $portfolio = $this->portfolioWithItems([]);

        $this->assertEquals(0, $portfolio->calculateCurrentValue());
        $this->assertEquals(0, $portfolio->calculateTotalInvested());
    }

    #[Group('portfolioTotals')]
    public function test_total_profit_loss_is_current_value_minus_total_invested(): void
    {
        $portfolio = new Portfolio(['total_invested' => 1000, 'current_value' => 1250]);

        $this->assertEqualsWithDelta(250.0, $portfolio->total_profit_loss, 0.001);
        $this->assertTrue($portfolio->is_positive);
    }

    #[Group('portfolioTotals')]
    public function test_total_profit_loss_percent_is_zero_when_nothing_invested_yet(): void
    {
        // Guards the division-by-zero case for a brand new, empty portfolio.
        $portfolio = new Portfolio(['total_invested' => 0, 'current_value' => 0]);

        $this->assertEquals(0, $portfolio->total_profit_loss_percent);
    }

    #[Group('portfolioTotals')]
    public function test_total_profit_loss_percent_computed_correctly(): void
    {
        $portfolio = new Portfolio(['total_invested' => 1000, 'current_value' => 1100]);

        $this->assertEqualsWithDelta(10.0, $portfolio->total_profit_loss_percent, 0.001);
    }

    #[Group('portfolioTotals')]
    public function test_is_positive_false_for_a_loss(): void
    {
        $portfolio = new Portfolio(['total_invested' => 1000, 'current_value' => 900]);

        $this->assertFalse($portfolio->is_positive);
    }
}
