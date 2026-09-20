<?php

namespace Tests\Feature\Frontend\Services;

use App\Frontend\Repositories\PortfolioRepository;
use App\Frontend\Repositories\PortfolioTransactionRepository;
use App\Frontend\Services\PortfolioLedgerService;
use App\Models\Portfolio;
use App\Models\PortfolioItem;
use App\Models\PortfolioTransaction;
use App\Models\StockSymbol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Real DB: the value of these tests is the arithmetic on real rows (weighted average cost, realised P&L, undo).
 */
class PortfolioLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Portfolio $portfolio;
    private PortfolioLedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->portfolio = Portfolio::create(['user_id' => $this->user->id, 'name' => 'Test', 'total_invested' => 0, 'current_value' => 0, 'is_active' => true]);
        StockSymbol::create(['symbol' => 'FPT', 'name' => 'FPT Corp', 'exchange' => 'HSX']);
        $this->ledger = new PortfolioLedgerService(new PortfolioRepository, new PortfolioTransactionRepository);
    }

    private function trade(string $type, int $qty, float $price, float $fee = 0, string $symbol = 'FPT', string $date = '2026-09-10'): array
    {
        return $this->ledger->trade($this->portfolio->id, $this->user->id, [
            'type' => $type, 'stock_symbol' => $symbol, 'quantity' => $qty, 'price' => $price, 'fee' => $fee, 'traded_at' => $date,
        ]);
    }

    private function item(string $symbol = 'FPT'): ?PortfolioItem
    {
        return PortfolioItem::where(['portfolio_id' => $this->portfolio->id, 'stock_symbol' => $symbol])->first();
    }

    // ── buy ─────────────────────────────────────────────────────────────────

    #[Group('portfolioLedger')]
    public function test_a_first_buy_opens_the_position_with_the_fee_inside_the_cost(): void
    {
        $r = $this->trade('buy', 100, 80000, 12000);

        $this->assertTrue($r['ok']);
        $item = $this->item();
        $this->assertSame(100, $item->quantity);
        $this->assertEqualsWithDelta(80120.0, (float) $item->buy_price, 0.001);       // (100*80000 + 12000) / 100
        $this->assertSame('FPT Corp', $item->stock_name);                              // name comes from the symbol list
        $this->assertSame('2026-09-10', $item->buy_date->toDateString());
        $this->assertSame(1, PortfolioTransaction::count());
        $this->assertEqualsWithDelta(8_012_000.0, (float) $this->portfolio->fresh()->total_invested, 0.01);   // totals recomputed
    }

    #[Group('portfolioLedger')]
    public function test_a_second_buy_moves_the_weighted_average_and_keeps_the_earliest_date(): void
    {
        $this->trade('buy', 100, 80000, 0, 'FPT', '2026-09-10');
        $this->trade('buy', 300, 100000, 0, 'FPT', '2026-08-01');

        $item = $this->item();
        $this->assertSame(400, $item->quantity);
        $this->assertEqualsWithDelta(95000.0, (float) $item->buy_price, 0.001);       // (100*80k + 300*100k) / 400
        $this->assertSame('2026-08-01', $item->buy_date->toDateString());
    }

    // ── sell ────────────────────────────────────────────────────────────────

    #[Group('portfolioLedger')]
    public function test_a_partial_sell_freezes_realised_pl_and_keeps_the_average_cost(): void
    {
        $this->trade('buy', 400, 95000);

        $r = $this->trade('sell', 100, 110000, 275000);   // 0.25% of the 11M proceeds

        $this->assertTrue($r['ok']);
        $tx = PortfolioTransaction::where('type', 'sell')->first();
        $this->assertEqualsWithDelta(95000.0, $tx->cost_basis, 0.001);
        $this->assertEqualsWithDelta(100 * 110000 - 275000 - 100 * 95000, $tx->realized_pl, 0.01);   // 1,225,000
        $item = $this->item();
        $this->assertSame(300, $item->quantity);
        $this->assertEqualsWithDelta(95000.0, (float) $item->buy_price, 0.001);       // what is left keeps its cost
        $this->assertStringContainsString('lãi', $r['message']);
    }

    #[Group('portfolioLedger')]
    public function test_a_later_buy_never_rewrites_an_earlier_realised_result(): void
    {
        $this->trade('buy', 100, 80000);
        $this->trade('sell', 50, 90000);
        $realizedBefore = PortfolioTransaction::where('type', 'sell')->first()->realized_pl;

        $this->trade('buy', 500, 120000);

        $this->assertEqualsWithDelta($realizedBefore, PortfolioTransaction::where('type', 'sell')->first()->realized_pl, 0.001);
        $this->assertEqualsWithDelta(500_000.0, $realizedBefore, 0.01);
    }

    #[Group('portfolioLedger')]
    public function test_selling_everything_closes_the_position_and_a_loss_is_reported_as_a_loss(): void
    {
        $this->trade('buy', 100, 80000);

        $r = $this->trade('sell', 100, 70000);

        $this->assertNull($this->item());
        $this->assertEqualsWithDelta(-1_000_000.0, PortfolioTransaction::where('type', 'sell')->first()->realized_pl, 0.01);
        $this->assertStringContainsString('lỗ', $r['message']);
        $this->assertEqualsWithDelta(0.0, (float) $this->portfolio->fresh()->total_invested, 0.01);
    }

    #[Group('portfolioLedger')]
    public function test_you_cannot_sell_more_than_you_hold_or_something_you_do_not_own(): void
    {
        $this->trade('buy', 100, 80000);

        $over = $this->trade('sell', 101, 90000);
        $none = $this->trade('sell', 1, 90000, 0, 'ACB');

        $this->assertFalse($over['ok']);
        $this->assertSame(422, $over['status']);
        $this->assertStringContainsString('100', $over['message']);
        $this->assertFalse($none['ok']);
        $this->assertSame(100, $this->item()->quantity);
        $this->assertSame(1, PortfolioTransaction::count());      // nothing was recorded for the rejected trades
    }

    #[Group('portfolioLedger')]
    public function test_invalid_input_and_foreign_portfolios_are_rejected(): void
    {
        $this->assertSame(422, $this->trade('hold', 1, 1000)['status']);
        $this->assertSame(422, $this->trade('buy', 0, 1000)['status']);
        $this->assertSame(422, $this->trade('buy', 1, 0)['status']);

        $stranger = User::factory()->create();
        $r = $this->ledger->trade($this->portfolio->id, $stranger->id, ['type' => 'buy', 'stock_symbol' => 'FPT', 'quantity' => 1, 'price' => 1000]);
        $this->assertSame(404, $r['status']);
        $this->assertSame(0, PortfolioTransaction::count());
    }

    // ── undo ────────────────────────────────────────────────────────────────

    #[Group('portfolioLedger')]
    public function test_undoing_a_sell_restores_the_quantity_and_even_a_closed_position(): void
    {
        $this->trade('buy', 100, 80000);
        $this->trade('sell', 100, 90000);
        $this->assertNull($this->item());

        $sellId = PortfolioTransaction::where('type', 'sell')->value('id');
        $r = $this->ledger->undo($sellId, $this->user->id);

        $this->assertTrue($r['ok']);
        $item = $this->item();
        $this->assertSame(100, $item->quantity);
        $this->assertEqualsWithDelta(80000.0, (float) $item->buy_price, 0.001);
        $this->assertSame('2026-09-10', $item->buy_date->toDateString());
        $this->assertSame(1, PortfolioTransaction::count());
    }

    #[Group('portfolioLedger')]
    public function test_undoing_a_buy_reverses_the_weighted_average_exactly(): void
    {
        $this->trade('buy', 100, 80000, 0);
        $this->trade('buy', 300, 100000, 30000);
        $this->assertEqualsWithDelta(95075.0, (float) $this->item()->buy_price, 0.001);    // (8,000,000 + 30,030,000) / 400

        $this->ledger->undo(PortfolioTransaction::orderByDesc('id')->value('id'), $this->user->id);

        $item = $this->item();
        $this->assertSame(100, $item->quantity);
        $this->assertEqualsWithDelta(80000.0, (float) $item->buy_price, 0.01);
    }

    #[Group('portfolioLedger')]
    public function test_undoing_the_only_buy_removes_the_holding(): void
    {
        $this->trade('buy', 100, 80000);

        $this->ledger->undo(PortfolioTransaction::value('id'), $this->user->id);

        $this->assertNull($this->item());
        $this->assertSame(0, PortfolioTransaction::count());
    }

    #[Group('portfolioLedger')]
    public function test_only_the_newest_transaction_of_a_symbol_can_be_undone_and_only_by_its_owner(): void
    {
        $this->trade('buy', 100, 80000);
        $this->trade('sell', 40, 90000);
        $firstId = PortfolioTransaction::orderBy('id')->value('id');

        $old = $this->ledger->undo($firstId, $this->user->id);
        $stranger = $this->ledger->undo(PortfolioTransaction::orderByDesc('id')->value('id'), User::factory()->create()->id);

        $this->assertFalse($old['ok']);
        $this->assertStringContainsString('gần nhất', $old['message']);
        $this->assertSame(404, $stranger['status']);
        $this->assertSame(2, PortfolioTransaction::count());
        $this->assertSame(60, $this->item()->quantity);
    }

    #[Group('portfolioLedger')]
    public function test_undoing_a_buy_is_refused_when_the_holding_was_edited_below_that_quantity(): void
    {
        $this->trade('buy', 100, 80000);
        $this->item()->update(['quantity' => 30]);                  // manual edit through the holding form

        $r = $this->ledger->undo(PortfolioTransaction::value('id'), $this->user->id);

        $this->assertFalse($r['ok']);
        $this->assertSame(30, $this->item()->quantity);
    }

    #[Group('portfolioLedger')]
    public function test_the_container_gives_portfolio_service_its_optional_collaborators(): void
    {
        // Laravel does NOT resolve `?Service $x = null` parameters on its own — without the explicit binding the
        // ledger stayed null and the portfolio's upcoming-events list (CompanyProfileService) was silently empty.
        $service = app(\App\Frontend\Services\PortfolioService::class);
        $read = fn (string $prop) => (function () use ($prop) { return $this->$prop; })->call($service);

        $this->assertInstanceOf(\App\Frontend\Services\PortfolioLedgerService::class, $read('ledger'));
        $this->assertInstanceOf(\App\Frontend\Services\CompanyProfileService::class, $read('companyProfiles'));
    }

    // ── overview ────────────────────────────────────────────────────────────

    #[Group('portfolioLedger')]
    public function test_overview_summarises_realised_results_win_rate_and_the_undoable_rows(): void
    {
        $this->trade('buy', 1000, 80000, 0, 'FPT', '2026-09-01');
        $this->trade('sell', 100, 90000, 0, 'FPT', '2026-09-05');    // +1,000,000
        $this->trade('sell', 100, 70000, 0, 'FPT', '2026-09-06');    // -1,000,000...
        $this->trade('sell', 100, 100000, 50000, 'FPT', '2026-09-07'); // +1,950,000

        $o = $this->ledger->overview($this->portfolio->id);
        $s = $o['summary'];

        $this->assertEqualsWithDelta(1_950_000.0, $s['realized'], 0.01);
        $this->assertSame(3, $s['sells']);
        $this->assertSame(2, $s['wins']);
        $this->assertSame(1, $s['losses']);
        $this->assertEqualsWithDelta(66.7, $s['win_rate'], 0.05);
        $this->assertEqualsWithDelta(1_950_000.0, $s['best'], 0.01);
        $this->assertEqualsWithDelta(-1_000_000.0, $s['worst'], 0.01);
        $this->assertEqualsWithDelta(50000.0, $s['fees'], 0.01);
        $this->assertSame('FPT', $s['by_symbol'][0]['symbol']);
        $this->assertCount(4, $o['transactions']);
        $this->assertSame([PortfolioTransaction::max('id') => true], $o['undoable']);
    }

    #[Group('portfolioLedger')]
    public function test_an_empty_ledger_has_no_win_rate(): void
    {
        $s = $this->ledger->summary($this->portfolio->id);

        $this->assertNull($s['win_rate']);
        $this->assertSame(0.0, $s['realized']);
        $this->assertNull($s['best']);
    }
}
