<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Frontend\Services\MarketOverviewService;
use App\Models\ExchangeRate;
use App\Models\HotIndustry;
use App\Models\StockSymbol;
use App\Models\User;
use App\Models\WatchlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\BuildsMarketPayload;
use Tests\TestCase;

/**
 * Home market section, the ticker tape in the layout, /market/data and the watchlist endpoints — real routes and
 * DB. The Python boundary is never reached: snapshots are seeded, and where a request could reach it the
 * service is replaced by a stub.
 */
class MarketHomeAndWatchlistTest extends TestCase
{
    use RefreshDatabase, BuildsMarketPayload;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        Cache::flush();
        // home() needs these two so it never falls back to Python (see ExampleTest)
        HotIndustry::create(['symbol' => 'VCB', 'organ_name' => 'Vietcombank', 'icb_name3' => 'Ngân hàng']);
        ExchangeRate::create(['currency_code' => 'USD', 'currency_name' => 'US DOLLAR', 'buy_cash' => '25000', 'buy_transfer' => '25050', 'sell' => '25400', 'date' => now()->format('Y-m-d')]);
        foreach (['FPT' => 'FPT Corp', 'HPG' => 'Hòa Phát'] as $s => $n) {
            StockSymbol::create(['symbol' => $s, 'name' => $n, 'exchange' => 'HSX']);
        }
    }

    // ── home ────────────────────────────────────────────────────────────────

    #[Group('marketOverview')]
    public function test_home_shows_indices_breadth_liquidity_and_the_movers_data(): void
    {
        $this->seedMarketSnapshot();

        $r = $this->get('/');

        $r->assertOk();
        $r->assertSee('Tổng quan thị trường');
        $r->assertSee('1.815,66', false);
        $r->assertSee('VN30');
        $r->assertSee('Độ rộng thị trường');
        $r->assertSee('22,9 nghìn tỷ');
        $r->assertSee('id="mkChart"', false);
        $r->assertSee('window.__MARKET__', false);
        $r->assertSee('"NVB"', false);                              // movers travel to the client as JSON
    }

    #[Group('marketOverview')]
    public function test_home_offers_guests_a_login_prompt_and_users_their_watchlist_link(): void
    {
        $this->seedMarketSnapshot();

        $this->get('/')->assertSee('Theo dõi cổ phiếu yêu thích')->assertDontSee('Xem tất cả');

        $this->actingAs(User::factory()->create());
        $this->get('/')->assertSee('Xem tất cả')->assertDontSee('Theo dõi cổ phiếu yêu thích');
    }

    #[Group('marketOverview')]
    public function test_home_still_renders_when_there_is_no_market_data_at_all(): void
    {
        $stub = Mockery::mock(MarketOverviewService::class)->makePartial();
        $stub->shouldReceive('overview')->andReturn(['has_data' => false, 'error' => 'Nguồn dữ liệu sập', 'market_open' => false]);
        $stub->shouldReceive('ticker')->andReturn([]);
        $this->app->instance(MarketOverviewService::class, $stub);

        $this->get('/')->assertOk()->assertSee('Chưa tải được dữ liệu thị trường')->assertSee('Nguồn dữ liệu sập');
    }

    #[Group('marketOverview')]
    public function test_home_survives_the_market_service_throwing(): void
    {
        $stub = Mockery::mock(MarketOverviewService::class)->makePartial();
        $stub->shouldReceive('overview')->andThrow(new \RuntimeException('boom'));
        $stub->shouldReceive('ticker')->andReturn([]);
        $this->app->instance(MarketOverviewService::class, $stub);

        $this->get('/')->assertOk()->assertSee('Chưa tải được dữ liệu thị trường');
    }

    // ── ticker tape ─────────────────────────────────────────────────────────

    #[Group('marketOverview')]
    public function test_the_ticker_tape_shows_real_quotes_and_never_the_old_hard_coded_ones(): void
    {
        $this->seedMarketSnapshot();

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('class="ticker-item"', $html);
        $this->assertStringContainsString('VN-INDEX', $html);
        $this->assertStringContainsString('1.815,66', $html);
        $this->assertStringContainsString('0,39%', $html);
        $this->assertStringNotContainsString('Dữ liệu được cập nhật tự động mỗi ngày', $html);
    }

    #[Group('marketOverview')]
    public function test_the_ticker_tape_degrades_to_a_static_note_without_a_snapshot(): void
    {
        $stub = Mockery::mock(MarketOverviewService::class)->makePartial();
        $stub->shouldReceive('overview')->andReturn(['has_data' => false, 'error' => null, 'market_open' => false]);
        $this->app->instance(MarketOverviewService::class, $stub);

        $this->get('/')->assertOk()->assertSee('ticker-static', false)->assertSee('Giờ GD');
    }

    // ── /market/data ────────────────────────────────────────────────────────

    #[Group('marketOverview')]
    public function test_market_data_json_has_everything_the_page_polls_for(): void
    {
        $this->seedMarketSnapshot();

        $this->getJson('/market/data')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('trade_date', '2026-09-18')
            ->assertJsonPath('indices.0.code', 'VNINDEX')
            ->assertJsonPath('breadth.advancers', 367)
            ->assertJsonPath('movers.ALL.gainers.0.symbol', 'NVB')
            ->assertJsonPath('watchlist', null);
    }

    #[Group('marketOverview')]
    public function test_market_data_includes_the_signed_in_users_watchlist_only(): void
    {
        $this->seedMarketSnapshot();
        $me = User::factory()->create();
        $other = User::factory()->create();
        WatchlistItem::create(['user_id' => $me->id, 'symbol' => 'FPT']);
        WatchlistItem::create(['user_id' => $other->id, 'symbol' => 'HPG']);

        $this->actingAs($me)->getJson('/market/data')
            ->assertOk()
            ->assertJsonCount(1, 'watchlist')
            ->assertJsonPath('watchlist.0.symbol', 'FPT')
            ->assertJsonPath('watchlist.0.price', 71700);
    }

    #[Group('marketOverview')]
    public function test_market_data_is_503_with_a_message_when_nothing_could_be_loaded(): void
    {
        $stub = Mockery::mock(MarketOverviewService::class)->makePartial();
        $stub->shouldReceive('overview')->andReturn(['has_data' => false, 'error' => 'down', 'market_open' => false]);
        $this->app->instance(MarketOverviewService::class, $stub);

        $this->getJson('/market/data')->assertStatus(503)->assertJsonPath('success', false)->assertJsonPath('message', 'down');
    }

    // ── watchlist endpoints ─────────────────────────────────────────────────

    #[Group('watchlist')]
    public function test_the_watchlist_needs_a_signed_in_user_but_not_a_verified_email(): void
    {
        $this->get('/watchlist')->assertRedirect(route('login'));
        $this->postJson('/watchlist', ['symbol' => 'FPT'])->assertUnauthorized();
        $this->getJson('/watchlist/data')->assertUnauthorized();

        $this->seedMarketSnapshot();
        $this->actingAs(User::factory()->unverified()->create());
        $this->get('/watchlist')->assertOk()->assertSee('Danh sách theo dõi');
    }

    #[Group('watchlist')]
    public function test_follow_and_unfollow_round_trip(): void
    {
        $this->seedMarketSnapshot();
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/watchlist', ['symbol' => 'fpt'])
            ->assertOk()->assertJsonPath('watched', true)->assertJsonPath('symbol', 'FPT');
        $this->assertSame(1, WatchlistItem::where('user_id', $user->id)->count());

        $this->getJson('/watchlist/data')->assertOk()->assertJsonPath('rows.0.symbol', 'FPT')->assertJsonPath('rows.0.percent', -3.5);

        $this->deleteJson('/watchlist/FPT')->assertOk()->assertJsonPath('watched', false);
        $this->assertSame(0, WatchlistItem::count());
        $this->deleteJson('/watchlist/FPT')->assertOk();   // unfollowing twice is harmless
    }

    #[Group('watchlist')]
    public function test_following_an_unknown_symbol_is_a_422_with_a_helpful_message(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/watchlist', ['symbol' => 'NOPE'])->assertStatus(422)->assertJsonPath('success', false);
        $this->postJson('/watchlist', [])->assertStatus(422);
        $this->assertSame(0, WatchlistItem::count());
    }

    #[Group('watchlist')]
    public function test_the_page_renders_the_users_rows_as_json_and_never_someone_elses(): void
    {
        $this->seedMarketSnapshot();
        $me = User::factory()->create();
        WatchlistItem::create(['user_id' => $me->id, 'symbol' => 'FPT']);
        WatchlistItem::create(['user_id' => User::factory()->create()->id, 'symbol' => 'HPG']);

        $html = $this->actingAs($me)->get('/watchlist')->getContent();

        $this->assertStringContainsString('"symbol":"FPT"', $html);
        $this->assertStringNotContainsString('"symbol":"HPG"', $html);
        $this->assertStringContainsString('id="wlAddForm"', $html);
    }

    #[Group('watchlist')]
    public function test_stock_and_company_pages_carry_the_follow_button_state(): void
    {
        $user = User::factory()->create();
        WatchlistItem::create(['user_id' => $user->id, 'symbol' => 'FPT']);

        $this->actingAs($user);
        $html = $this->get('/company/FPT')->getContent();

        $this->assertStringContainsString('data-watch="FPT"', $html);
        $this->assertStringContainsString('"watched":["FPT"]', $html);
    }
}
