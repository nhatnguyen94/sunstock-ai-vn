<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Jobs\ProcessStockPriceSync;
use App\Models\Portfolio;
use App\Models\PortfolioItem;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Models\StockSymbol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Real DB + real routes (RefreshDatabase): what matters here is the whole request path — validation,
 * ownership checks, the price-unit conversion against real stock_prices rows, redirects. Pages extend
 * layouts.app (navbar queries news_categories), hence RefreshDatabase. Queue::fake() keeps every
 * background job (price sync, company profile warm-up) from spawning Python.
 */
class PortfolioControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        Cache::flush();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** Symbol list entry + a Stock with two sessions of prices, in FEED units (thousands of VND). */
    private function market(string $symbol, float $close, ?float $prev = null, string $name = 'Ngân hàng Test'): Stock
    {
        StockSymbol::create(['symbol' => $symbol, 'name' => $name, 'exchange' => 'HSX']);
        $stock = Stock::create(['symbol' => $symbol]);
        if ($prev !== null) {
            StockPrice::create(['stock_id' => $stock->id, 'date' => '2026-09-10', 'open' => $prev, 'high' => $prev, 'low' => $prev, 'close' => $prev, 'volume' => 1]);
        }
        StockPrice::create(['stock_id' => $stock->id, 'date' => '2026-09-11', 'open' => $close, 'high' => $close, 'low' => $close, 'close' => $close, 'volume' => 1]);

        return $stock;
    }

    private function portfolio(?User $owner = null, string $name = 'Dài hạn'): Portfolio
    {
        return Portfolio::create(['user_id' => ($owner ?? $this->user)->id, 'name' => $name, 'total_invested' => 0, 'current_value' => 0, 'is_active' => true]);
    }

    private function holding(Portfolio $p, string $symbol = 'ACB', array $over = []): PortfolioItem
    {
        $item = PortfolioItem::create(array_merge([
            'portfolio_id' => $p->id, 'stock_symbol' => $symbol, 'stock_name' => 'Test', 'quantity' => 200,
            'buy_price' => 20000, 'current_price' => 20000, 'buy_date' => '2026-06-01',
        ], $over));
        $p->recalculateTotals();

        return $item;
    }

    // ── the unit bug ────────────────────────────────────────────────────────

    #[Group('portfolioPage')]
    public function test_show_values_holdings_in_whole_vnd_not_in_the_feeds_thousands(): void
    {
        $this->market('ACB', 22.05, 22.40);   // feed says 22.05  ==  22,050 VND
        $p = $this->portfolio();
        $this->holding($p, 'ACB', ['buy_price' => 20000, 'current_price' => 20000]);

        $response = $this->get("/portfolio/{$p->id}");

        $response->assertOk();
        $response->assertSee('22.050₫');          // price shown in VND
        $response->assertDontSee('22₫');           // the old bug: 22.05 shown as "22đ"
        $item = PortfolioItem::first();
        $this->assertEquals(22050, $item->current_price);
        $this->assertEquals(22400, $item->previous_price);
        $this->assertSame('2026-09-11', $item->price_date->toDateString());
        // 200 * (22050 - 20000) = +410,000 — not the -99.9% the raw feed unit produced
        $this->assertEquals(200 * 22050, $p->fresh()->current_value);
    }

    #[Group('portfolioPage')]
    public function test_show_reports_todays_move_from_the_previous_close(): void
    {
        $this->market('ACB', 22.05, 22.00);
        $p = $this->portfolio();
        $this->holding($p, 'ACB');

        $this->get("/portfolio/{$p->id}")->assertOk()->assertSee('+10.000₫');   // 200 * (22050 - 22000)
    }

    // ── ownership ───────────────────────────────────────────────────────────

    #[Group('portfolioPage')]
    public function test_other_users_portfolios_are_invisible_and_untouchable(): void
    {
        $other = User::factory()->create();
        $theirs = $this->portfolio($other, 'Của người khác');
        $item = $this->holding($theirs);

        $this->get("/portfolio/{$theirs->id}")->assertNotFound();
        $this->postJson("/portfolio/{$theirs->id}/update-prices")->assertNotFound();
        $this->get("/portfolio/{$theirs->id}/export")->assertNotFound();
        $this->put("/portfolio/item/{$item->id}", ['quantity' => 1, 'buy_price' => 1000])->assertSessionHasErrors('error');
        $this->assertSame(200, $item->fresh()->quantity);

        $this->delete("/portfolio/item/{$item->id}");
        $this->assertNotNull(PortfolioItem::find($item->id));
    }

    // ── refresh button ──────────────────────────────────────────────────────

    #[Group('portfolioPage')]
    public function test_update_prices_says_what_it_did_and_queues_symbols_that_have_no_data(): void
    {
        $this->market('ACB', 22.05, 22.40);
        StockSymbol::create(['symbol' => 'NEWCO', 'name' => 'Mới niêm yết', 'exchange' => 'HNX']);   // known symbol, no prices yet
        $p = $this->portfolio();
        $this->holding($p, 'ACB');
        $this->holding($p, 'NEWCO');

        $response = $this->postJson("/portfolio/{$p->id}/update-prices");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('missing.0', 'NEWCO')
            ->assertJsonPath('as_of', '2026-09-11');
        $this->assertStringContainsString('NEWCO', $response->json('message'));
        Queue::assertPushed(ProcessStockPriceSync::class, 1);

        // pressing it again must not stack more jobs for the same symbol
        $this->postJson("/portfolio/{$p->id}/update-prices")->assertOk();
        Queue::assertPushed(ProcessStockPriceSync::class, 1);
    }

    // ── edit / delete a holding ─────────────────────────────────────────────

    #[Group('portfolioPage')]
    public function test_a_holding_can_be_edited_including_target_and_stop_loss(): void
    {
        $p = $this->portfolio();
        $item = $this->holding($p);

        $this->put("/portfolio/item/{$item->id}", [
            'quantity' => 350, 'buy_price' => 21000, 'target_price' => 30000, 'stop_loss_price' => 18000, 'notes' => 'giữ dài hạn',
        ])->assertRedirect("/portfolio/{$p->id}")->assertSessionHas('success');

        $item->refresh();
        $this->assertSame(350, $item->quantity);
        $this->assertEquals(30000, $item->target_price);
        $this->assertEquals(18000, $item->stop_loss_price);
        $this->assertEquals(350 * 21000, $p->fresh()->total_invested);   // totals recomputed
    }

    #[Group('portfolioPage')]
    public function test_editing_rejects_prices_that_look_like_thousands(): void
    {
        $p = $this->portfolio();
        $item = $this->holding($p);

        $this->put("/portfolio/item/{$item->id}", ['quantity' => 10, 'buy_price' => 85])->assertSessionHasErrors('buy_price');
        $this->assertEquals(20000, $item->fresh()->buy_price);
    }

    #[Group('portfolioPage')]
    public function test_removing_a_holding_returns_to_its_portfolio(): void
    {
        // Regression: the redirect target used to be looked up with the ITEM id as if it were a PORTFOLIO id
        $filler = $this->portfolio($this->user, 'Danh mục 1');
        $p = $this->portfolio($this->user, 'Danh mục 2');
        $item = $this->holding($p);

        $this->delete("/portfolio/item/{$item->id}")
            ->assertRedirect("/portfolio/{$p->id}")
            ->assertSessionHas('success');

        $this->assertNull(PortfolioItem::find($item->id));
        $this->assertEquals(0, $p->fresh()->total_invested);
        $this->assertNotNull($filler->fresh());
    }

    // ── add a holding ───────────────────────────────────────────────────────

    #[Group('portfolioPage')]
    public function test_adding_a_stock_fills_the_name_and_values_it_at_the_market_price(): void
    {
        $this->market('FPT', 72.7, 74.5, 'Công ty Cổ phần FPT');
        $p = $this->portfolio();

        $this->post("/portfolio/{$p->id}/add-stock", [
            'stock_symbol' => 'fpt', 'quantity' => 100, 'buy_price' => 70000, 'buy_date' => '2026-08-01',
        ])->assertRedirect("/portfolio/{$p->id}")->assertSessionHas('success');

        $item = PortfolioItem::firstOrFail();
        $this->assertSame('FPT', $item->stock_symbol);                // normalised
        $this->assertSame('Công ty Cổ phần FPT', $item->stock_name);  // from the symbol list, not typed
        $this->assertEquals(72700, $item->current_price);            // market price, not the buy price
        $this->assertEquals(74500, $item->previous_price);
        $this->assertEquals(100 * 72700, $p->fresh()->current_value);
    }

    #[Group('portfolioPage')]
    public function test_buying_the_same_stock_again_averages_the_cost_and_keeps_the_market_price(): void
    {
        $this->market('FPT', 72.7, 74.5);
        $p = $this->portfolio();
        $payload = ['stock_symbol' => 'FPT', 'buy_date' => '2026-08-01'];

        $this->post("/portfolio/{$p->id}/add-stock", $payload + ['quantity' => 100, 'buy_price' => 70000]);
        $this->post("/portfolio/{$p->id}/add-stock", $payload + ['quantity' => 100, 'buy_price' => 80000]);

        $this->assertSame(1, PortfolioItem::count());
        $item = PortfolioItem::first();
        $this->assertSame(200, $item->quantity);
        $this->assertEquals(75000, $item->buy_price);
        $this->assertEquals(72700, $item->current_price);
    }

    #[Group('portfolioPage')]
    public function test_adding_validates_symbol_price_unit_and_date(): void
    {
        $this->market('FPT', 72.7);
        $p = $this->portfolio();
        $ok = ['stock_symbol' => 'FPT', 'quantity' => 10, 'buy_price' => 70000, 'buy_date' => '2026-08-01'];

        $this->post("/portfolio/{$p->id}/add-stock", array_merge($ok, ['stock_symbol' => 'NOPE']))->assertSessionHasErrors('stock_symbol');
        $this->post("/portfolio/{$p->id}/add-stock", array_merge($ok, ['buy_price' => 70]))->assertSessionHasErrors('buy_price');   // "70" ≠ 70,000₫
        $this->post("/portfolio/{$p->id}/add-stock", array_merge($ok, ['quantity' => 0]))->assertSessionHasErrors('quantity');
        $this->post("/portfolio/{$p->id}/add-stock", array_merge($ok, ['buy_date' => now()->addDay()->toDateString()]))->assertSessionHasErrors('buy_date');
        $this->assertSame(0, PortfolioItem::count());
    }

    // ── quote endpoint / quick add ──────────────────────────────────────────

    #[Group('portfolioPage')]
    public function test_quote_returns_name_and_vnd_price_or_404(): void
    {
        $this->market('FPT', 72.7, 74.5, 'Công ty Cổ phần FPT');

        $this->getJson('/portfolio/quote/fpt')
            ->assertOk()
            ->assertJsonPath('symbol', 'FPT')
            ->assertJsonPath('name', 'Công ty Cổ phần FPT')
            ->assertJsonPath('price', 72700)
            ->assertJsonPath('date', '2026-09-11');

        $this->getJson('/portfolio/quote/NOPE')->assertNotFound()->assertJsonPath('success', false);
    }

    #[Group('portfolioPage')]
    public function test_quick_add_routes_by_how_many_portfolios_the_user_has(): void
    {
        // none -> create one first, carrying the symbol along
        $this->get('/portfolio/add?symbol=fpt')->assertRedirect(route('portfolio.create', ['symbol' => 'FPT']));

        // exactly one -> straight to its add form
        $one = $this->portfolio($this->user, 'Duy nhất');
        $this->get('/portfolio/add?symbol=FPT')->assertRedirect(route('portfolio.add-stock', ['id' => $one->id, 'symbol' => 'FPT']));

        // several -> choose
        $this->portfolio($this->user, 'Thứ hai');
        $this->get('/portfolio/add?symbol=FPT')->assertOk()->assertViewIs('portfolio.choose')->assertSee('Duy nhất')->assertSee('Thứ hai');

        // hostile symbol is dropped, not echoed
        $this->get('/portfolio/add?symbol=' . urlencode('<script>'))->assertOk()->assertDontSee('<script>', false);
    }

    #[Group('portfolioPage')]
    public function test_creating_a_portfolio_from_quick_add_continues_to_the_add_form(): void
    {
        $response = $this->post('/portfolio', ['name' => 'Mới', 'symbol' => 'fpt']);

        $p = Portfolio::firstOrFail();
        $response->assertRedirect(route('portfolio.add-stock', ['id' => $p->id, 'symbol' => 'FPT']));
    }

    // ── export / edit portfolio / index ─────────────────────────────────────

    #[Group('portfolioPage')]
    public function test_export_is_a_utf8_csv_with_one_row_per_holding(): void
    {
        $this->market('ACB', 22.05, 22.40);
        $p = $this->portfolio(name: 'Dài hạn');
        $this->holding($p, 'ACB');

        $response = $this->get("/portfolio/{$p->id}/export");

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);        // BOM so Excel reads Vietnamese
        $this->assertStringContainsString('ACB', $csv);
        $this->assertStringContainsString('22050', $csv);
        $this->assertSame(2, substr_count(trim($csv), "\n") + 1);   // header + 1 holding
    }

    #[Group('portfolioPage')]
    public function test_a_portfolio_can_be_deactivated_from_the_edit_form(): void
    {
        // Regression: an unchecked checkbox is not submitted, so the value never changed to false
        $p = $this->portfolio();

        $this->put("/portfolio/{$p->id}", ['name' => 'Dài hạn', 'is_active' => '0'])->assertRedirect();

        $this->assertFalse($p->fresh()->is_active);
    }

    #[Group('portfolioPage')]
    public function test_index_shows_totals_for_active_portfolios_and_an_onboarding_state_when_empty(): void
    {
        $this->get('/portfolio')->assertOk()->assertSee('Bắt đầu theo dõi danh mục của bạn');

        $this->market('ACB', 22.05);
        $p = $this->portfolio(name: 'Cổ tức');
        $this->holding($p, 'ACB');

        $this->get('/portfolio')->assertOk()->assertSee('Cổ tức')->assertSee('4.410.000₫');   // 200 * 22,050
    }

    #[Group('portfolioPage')]
    public function test_the_navbar_offers_the_portfolio_to_guests_too(): void
    {
        auth()->logout();

        $this->get('/news')->assertOk()->assertSee(route('portfolio.index'), false);
        $this->get('/portfolio')->assertRedirect(route('login'));
    }
}
