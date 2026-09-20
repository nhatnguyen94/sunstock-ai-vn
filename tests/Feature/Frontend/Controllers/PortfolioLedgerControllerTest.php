<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Models\Portfolio;
use App\Models\PortfolioItem;
use App\Models\PortfolioTransaction;
use App\Models\StockSymbol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** Buy/sell ledger through the real routes: validation, ownership, redirects + flash messages, page, CSV, undo. */
class PortfolioLedgerControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Portfolio $portfolio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        Cache::flush();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->portfolio = Portfolio::create(['user_id' => $this->user->id, 'name' => 'Dài hạn', 'total_invested' => 0, 'current_value' => 0, 'is_active' => true]);
        StockSymbol::create(['symbol' => 'FPT', 'name' => 'FPT Corp', 'exchange' => 'HSX']);
    }

    private function trade(array $over = [])
    {
        return $this->from("/portfolio/{$this->portfolio->id}")->post("/portfolio/{$this->portfolio->id}/transactions", $over + [
            'type' => 'buy', 'stock_symbol' => 'fpt', 'quantity' => 100, 'price' => 80000, 'fee' => 120000, 'traded_at' => '2026-09-10',
        ]);
    }

    #[Group('portfolioLedger')]
    public function test_recording_a_buy_creates_the_holding_and_flashes_a_message(): void
    {
        $this->trade()->assertRedirect(route('portfolio.show', $this->portfolio->id))->assertSessionHas('success');

        $item = PortfolioItem::first();
        $this->assertSame('FPT', $item->stock_symbol);
        $this->assertSame(100, $item->quantity);
        $this->assertEqualsWithDelta(81200.0, (float) $item->buy_price, 0.01);
        $this->assertSame(1, PortfolioTransaction::count());
    }

    #[Group('portfolioLedger')]
    public function test_recording_a_sell_reports_the_realised_result(): void
    {
        $this->trade();

        $this->trade(['type' => 'sell', 'quantity' => 40, 'price' => 90000, 'fee' => 9000])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'lãi'));

        $this->assertSame(60, PortfolioItem::first()->quantity);
        $this->assertEqualsWithDelta(40 * 90000 - 9000 - 40 * 81200, PortfolioTransaction::where('type', 'sell')->first()->realized_pl, 0.01);
    }

    #[Group('portfolioLedger')]
    public function test_overselling_is_rejected_with_a_readable_error_and_records_nothing(): void
    {
        $this->trade();

        $this->trade(['type' => 'sell', 'quantity' => 500, 'price' => 90000])
            ->assertRedirect(route('portfolio.show', $this->portfolio->id))
            ->assertSessionHasErrors('error');

        $this->assertSame(100, PortfolioItem::first()->quantity);
        $this->assertSame(1, PortfolioTransaction::count());
    }

    #[Group('portfolioLedger')]
    public function test_validation_catches_price_in_thousands_unknown_symbols_and_future_dates(): void
    {
        $this->trade(['price' => 85])->assertSessionHasErrors('price');            // typed 85 for 85,000
        $this->trade(['stock_symbol' => 'NOPE'])->assertSessionHasErrors('stock_symbol');
        $this->trade(['traded_at' => now()->addDay()->toDateString()])->assertSessionHasErrors('traded_at');
        $this->trade(['quantity' => 0])->assertSessionHasErrors('quantity');
        $this->trade(['type' => 'swap'])->assertSessionHasErrors('type');
        $this->trade(['fee' => -5])->assertSessionHasErrors('fee');

        $this->assertSame(0, PortfolioTransaction::count());
    }

    #[Group('portfolioLedger')]
    public function test_someone_elses_portfolio_is_a_404(): void
    {
        $other = Portfolio::create(['user_id' => User::factory()->create()->id, 'name' => 'Của người khác', 'total_invested' => 0, 'current_value' => 0, 'is_active' => true]);

        $this->post("/portfolio/{$other->id}/transactions", ['type' => 'buy', 'stock_symbol' => 'FPT', 'quantity' => 1, 'price' => 80000, 'traded_at' => '2026-09-10'])->assertNotFound();
        $this->get("/portfolio/{$other->id}/transactions/export")->assertNotFound();
        $this->assertSame(0, PortfolioTransaction::count());
    }

    #[Group('portfolioLedger')]
    public function test_the_add_stock_form_also_writes_the_ledger(): void
    {
        $this->post("/portfolio/{$this->portfolio->id}/add-stock", [
            'stock_symbol' => 'FPT', 'quantity' => 200, 'buy_price' => 70000, 'buy_date' => '2026-09-01',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $tx = PortfolioTransaction::first();
        $this->assertSame('buy', $tx->type);
        $this->assertSame(200, $tx->quantity);
        $this->assertSame('2026-09-01', $tx->traded_at->toDateString());
        $this->assertSame(1, PortfolioItem::count());
    }

    #[Group('portfolioLedger')]
    public function test_the_portfolio_page_shows_realised_pl_the_ledger_and_the_trade_buttons(): void
    {
        $this->trade();
        $this->trade(['type' => 'sell', 'quantity' => 40, 'price' => 90000, 'fee' => 9000]);

        $r = $this->get("/portfolio/{$this->portfolio->id}");

        $r->assertOk();
        $r->assertSee('Sổ giao dịch');
        $r->assertSee('Lãi / lỗ đã chốt');
        $r->assertSee('Đã chốt theo mã');
        $r->assertSee('id="pfTradeModal"', false);
        $r->assertSee('pf-sell', false);
        $r->assertSee('Hoàn tác giao dịch này');
        $r->assertSee('343.000', false);      // realised: 40*90,000 - 9,000 fee - 40*81,200 average cost
    }

    #[Group('portfolioLedger')]
    public function test_an_empty_ledger_explains_itself(): void
    {
        PortfolioItem::create(['portfolio_id' => $this->portfolio->id, 'stock_symbol' => 'FPT', 'stock_name' => 'FPT', 'quantity' => 1, 'buy_price' => 80000, 'current_price' => 80000, 'buy_date' => '2026-09-01']);

        $this->get("/portfolio/{$this->portfolio->id}")->assertOk()->assertSee('Chưa có giao dịch')->assertDontSee('Lãi / lỗ đã chốt');
    }

    #[Group('portfolioLedger')]
    public function test_undo_route_restores_the_holding_and_refuses_older_transactions(): void
    {
        $this->trade();
        $this->trade(['type' => 'sell', 'quantity' => 40, 'price' => 90000]);
        [$buy, $sell] = PortfolioTransaction::orderBy('id')->get()->all();

        $this->delete("/portfolio/transactions/{$buy->id}")->assertSessionHasErrors('error');
        $this->assertSame(2, PortfolioTransaction::count());

        $this->delete("/portfolio/transactions/{$sell->id}")->assertRedirect(route('portfolio.show', $this->portfolio->id))->assertSessionHas('success');
        $this->assertSame(100, PortfolioItem::first()->quantity);
        $this->assertSame(1, PortfolioTransaction::count());
    }

    #[Group('portfolioLedger')]
    public function test_undo_of_another_users_transaction_is_a_404(): void
    {
        $tx = PortfolioTransaction::create([
            'portfolio_id' => Portfolio::create(['user_id' => User::factory()->create()->id, 'name' => 'X', 'total_invested' => 0, 'current_value' => 0, 'is_active' => true])->id,
            'stock_symbol' => 'FPT', 'type' => 'buy', 'quantity' => 1, 'price' => 80000, 'traded_at' => '2026-09-10',
        ]);

        $this->delete("/portfolio/transactions/{$tx->id}")->assertNotFound();
        $this->assertSame(1, PortfolioTransaction::count());
    }

    #[Group('portfolioLedger')]
    public function test_the_ledger_csv_has_a_bom_headers_and_one_line_per_trade_oldest_first(): void
    {
        $this->trade(['traded_at' => '2026-09-10']);
        $this->trade(['type' => 'sell', 'quantity' => 40, 'price' => 90000, 'fee' => 9000, 'traded_at' => '2026-09-12']);

        $r = $this->get("/portfolio/{$this->portfolio->id}/transactions/export");

        $r->assertOk();
        $body = $r->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $lines = array_values(array_filter(explode("\n", trim($body))));
        $this->assertCount(3, $lines);
        $this->assertStringContainsString('Ngày', $lines[0]);
        $this->assertStringContainsString('2026-09-10,FPT,Mua,100', $lines[1]);
        $this->assertStringContainsString('2026-09-12,FPT,Bán,40', $lines[2]);
    }
}
