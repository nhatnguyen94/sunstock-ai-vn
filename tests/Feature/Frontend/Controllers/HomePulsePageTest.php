<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Frontend\Services\HomeDashboardService;
use App\Frontend\Services\MarketOverviewService;
use App\Frontend\Services\MarketPulseService;
use App\Frontend\Services\StockSignalService;
use App\Frontend\Services\WorldMarketService;
use App\Jobs\BuildStockSignalsJob;
use App\Jobs\SyncWorldMarketsJob;
use App\Models\CompanyProfile;
use App\Models\ExchangeRate;
use App\Models\Fund;
use App\Models\GoldPrice;
use App\Models\HotIndustry;
use App\Models\StockSymbol;
use App\Models\User;
use App\Models\WatchlistItem;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Concerns\BuildsMarketPayload;
use Tests\TestCase;

/**
 * The "Nhịp thị trường" additions on the home page, end to end on real routes: world strip, foreign flow, sentiment gauge, "Vàng · Tỷ giá · Quỹ",
 * and the Tín hiệu / Sự kiện tabs. Everything is seeded (snapshot, caches, tables): no Python, no network, no heavy build.
 */
class HomePulsePageTest extends TestCase
{
    use BuildsMarketPayload, RefreshDatabase;

    private const FOREIGN = [
        'buy_value' => 1_796_000_000_000, 'sell_value' => 2_165_000_000_000, 'net_value' => -369_000_000_000,
        'by_exchange' => ['HOSE' => ['buy' => 1, 'sell' => 2, 'net' => -367_000_000_000], 'HNX' => ['buy' => 1, 'sell' => 0, 'net' => 9_800_000_000], 'UPCOM' => ['buy' => 0, 'sell' => 1, 'net' => -11_600_000_000]],
        'top_buy' => [['symbol' => 'PLX', 'exchange' => 'HOSE', 'price' => 37600, 'buy_volume' => 1, 'sell_volume' => 0, 'net_volume' => 1, 'net_value' => 91_500_000_000]],
        'top_sell' => [['symbol' => 'TCB', 'exchange' => 'HOSE', 'price' => 31900, 'buy_volume' => 0, 'sell_volume' => 1, 'net_volume' => -1, 'net_value' => -151_800_000_000]],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
        HotIndustry::create(['symbol' => 'VCB', 'organ_name' => 'Vietcombank', 'icb_name3' => 'Ngân hàng']);
        ExchangeRate::create(['currency_code' => 'USD', 'currency_name' => 'US DOLLAR', 'buy_cash' => '25,720.00', 'buy_transfer' => '25,720.00', 'sell' => '26,100.00', 'date' => now()->format('Y-m-d')]);
        StockSymbol::create(['symbol' => 'FPT', 'name' => 'FPT Corp', 'exchange' => 'HSX', 'industry' => 'Công nghệ và thông tin']);
    }

    private function seedAll(): void
    {
        $this->seedMarketSnapshot(['foreign' => self::FOREIGN]);
        Cache::put(WorldMarketService::CACHE_KEY, [
            'markets' => [
                ['code' => 'INX', 'name' => 'S&P 500', 'region' => 'Mỹ', 'close' => 7801.77, 'previous' => 7818.93, 'change' => -17.16, 'percent' => -0.22, 'date' => '2026-10-07', 'series' => [7700.0, 7818.93, 7801.77]],
                ['code' => 'N225', 'name' => 'Nikkei 225', 'region' => 'Nhật', 'close' => 69042.11, 'previous' => 68000.0, 'change' => 1042.11, 'percent' => 1.53, 'date' => '2026-10-07', 'series' => [68000.0, 69042.11]],
            ],
            'synced_at' => now()->toIso8601String(),
        ], now()->addDay());
        Cache::put(StockSignalService::CACHE_KEY, [
            'universe' => 200, 'trade_date' => '2026-10-08', 'built_at' => now()->toIso8601String(),
            'breakout' => [['s' => 'MSR', 'p' => 68200, 'c' => 5.9, 'v' => 166_000_000_000, 'ref' => 66300, 'over' => 2.87]], 'breakout_total' => 1,
            'breakdown' => [['s' => 'SHB', 'p' => 10050, 'c' => -6.94, 'v' => 461_000_000_000, 'ref' => 10650, 'under' => 5.97]], 'breakdown_total' => 6,
            'volume' => [['s' => 'HMC', 'p' => 10700, 'c' => 1.9, 'v' => 13_000_000_000, 'ratio' => 167.6]], 'volume_total' => 30,
            'overbought' => [['s' => 'GSP', 'p' => 11200, 'c' => 1.36, 'v' => 5_800_000_000, 'rsi' => 87.2]], 'overbought_total' => 19,
            'oversold' => [], 'oversold_total' => 0,
        ], now()->addDay());
        GoldPrice::create(['source' => 'SJC', 'metal' => 'gold', 'product' => 'Vàng miếng 1L', 'branch' => 'Hồ Chí Minh', 'unit' => 'lượng', 'buy_price' => 140_500_000, 'sell_price' => 143_500_000, 'world_price' => 4378.0, 'quoted_at' => now(), 'synced_at' => now()]);
        Fund::create(['short_name' => 'TBLF', 'name' => 'QUỸ BALLAD', 'type_code' => Fund::TYPE_STOCK, 'nav' => 1, 'nav_change_12m' => 3.76, 'fund_id_fmarket' => 1]);
    }

    private function dom(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($doc);
    }

    // ── world strip ─────────────────────────────────────────────────────────

    #[Group('homePulse')]
    public function test_the_world_strip_sits_under_the_indices_with_one_chip_per_market(): void
    {
        $this->seedAll();

        $html = $this->get('/')->assertOk()->getContent();
        $xp = $this->dom($html);

        $this->assertSame(2, $xp->query("//*[@id='mkWorld']//*[contains(@class,'mk-w ')]")->length);
        $this->assertStringContainsString('S&amp;P 500', $html);
        $this->assertStringContainsString('7.801,77', $html);
        $this->assertStringContainsString('mk-w down', $html);
        $this->assertStringContainsString('mk-w up', $html);
        $this->assertSame(1, $xp->query("//*[@id='hpMarkets']//*[@id='mkWorld']")->length, 'the strip lives in the Thị trường tab');
        $this->assertTrue(strpos($html, 'class="mk-indices"') < strpos($html, 'id="mkWorld"'));
    }

    #[Group('homePulse')]
    public function test_without_a_world_answer_the_strip_is_absent_and_one_refresh_is_queued(): void
    {
        $this->seedMarketSnapshot();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="mkWorld"', $html);
        Queue::assertPushed(SyncWorldMarketsJob::class, 1);
    }

    // ── the three cards ─────────────────────────────────────────────────────

    #[Group('homePulse')]
    public function test_the_three_cards_follow_the_lists_in_the_markets_tab(): void
    {
        $this->seedAll();

        $html = $this->get('/')->assertOk()->getContent();

        foreach (['mkForeignCard', 'mkSentimentCard', 'mkPulseCard'] as $id) {
            $this->assertStringContainsString("id=\"{$id}\"", $html);
        }
        $this->assertTrue(strpos($html, 'id="aiPredictBtn"') < strpos($html, 'id="mkSide"') && strpos($html, 'id="mkSide"') < strpos($html, 'id="mkPulse"'), 'AI button, the lists, then the three cards');
        $this->assertSame(1, $this->dom($html)->query("//*[@id='hpMarkets']//*[@id='mkPulse']")->length);
        $this->assertSame(3, $this->dom($html)->query("//*[@id='mkPulse']/*[contains(@class,'col-lg-4')]")->length, 'three cards in thirds');
    }

    #[Group('homePulse')]
    public function test_foreign_flow_and_sentiment_travel_to_the_script_as_data(): void
    {
        $this->seedAll();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('"foreign":{"buy_value":1796000000000', $html);
        $this->assertStringContainsString('"sentiment":{"score":', $html);
        $this->assertStringContainsString('"net_value":-369000000000', $html);
        $this->assertStringContainsString('id="mkForeign"', $html);
        $this->assertStringContainsString('id="mkSentiment"', $html);
        $this->assertStringContainsString('ước tính', $html);
    }

    #[Group('homePulse')]
    public function test_the_poll_brings_fresh_foreign_flow_and_sentiment(): void
    {
        $this->seedAll();

        $json = $this->getJson('/market/data')->assertOk()->json();

        $this->assertSame(-369_000_000_000, $json['foreign']['net_value']);
        $this->assertSame('PLX', $json['foreign']['top_buy'][0]['symbol']);
        $this->assertIsInt($json['sentiment']['score']);
        $this->assertContains($json['sentiment']['label'], ['Sợ hãi cực độ', 'Sợ hãi', 'Trung lập', 'Tham lam', 'Tham lam cực độ']);
        $this->assertSame(['breadth', 'momentum', 'limits', 'foreign'], array_column($json['sentiment']['components'], 'key'));
    }

    #[Group('homePulse')]
    public function test_gold_dollar_and_funds_are_shown_with_links_to_their_pages(): void
    {
        $this->seedAll();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Vàng SJC', $html);
        $this->assertStringContainsString('143,5 tr', $html);
        $this->assertStringContainsString('Thế giới 4.378 USD/oz', $html);
        $this->assertStringContainsString('USD (Vietcombank bán)', $html);
        $this->assertStringContainsString('26.100 ₫', $html);
        $this->assertStringContainsString('TBLF', $html);
        $this->assertStringContainsString('+3,76%', $html);
        foreach ([route('gold.index'), route('exchange-rate.index'), route('funds.index'), url('/funds/TBLF')] as $link) {
            $this->assertStringContainsString('href="'.$link.'"', $html);
        }
    }

    #[Group('homePulse')]
    public function test_a_card_without_data_is_left_out_and_the_others_share_the_row(): void
    {
        $this->seedMarketSnapshot();   // no foreign flow in this snapshot

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="mkForeignCard"', $html);
        $this->assertStringContainsString('id="mkSentimentCard"', $html);
        $this->assertStringContainsString('id="mkPulseCard"', $html);
        $this->assertStringContainsString('col-lg-6 mk-col', $html);
    }

    // ── Tín hiệu / Sự kiện ──────────────────────────────────────────────────

    #[Group('homePulse')]
    public function test_the_signals_tab_lists_each_signal_with_its_total_and_the_not_advice_disclaimer(): void
    {
        $this->seedAll();

        $html = $this->get('/')->assertOk()->getContent();
        $xp = $this->dom($html);

        $this->assertSame(1, $xp->query("//*[@id='hpSignals']//*[@id='signals']")->length, 'signals have a tab of the page, not an inner tab');
        $this->assertSame(0, $xp->query("//*[@id='exTabSignals']")->length);
        $this->assertSame(5, $xp->query("//*[@id='exPanelSignals']//*[contains(@class,'mk-sig ')]")->length);
        foreach (['Vượt đỉnh 52 tuần', 'Thủng đáy 52 tuần', 'Khối lượng đột biến', 'RSI quá mua', 'RSI quá bán', 'trên đỉnh 2,87%', 'dưới đáy 5,97%', 'RSI 87', 'không phải khuyến nghị mua bán', 'xét 200 cổ phiếu'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringContainsString('×99+ KL TB', $html, 'a silly ratio is shown as 99+');
        $this->assertStringContainsString('Không có mã nào', $html, 'an empty list says so');
        $this->assertStringContainsString('href="'.url('/stock?symbol=MSR').'"', $html);
    }

    #[Group('homePulse')]
    public function test_while_the_first_build_runs_the_signals_tab_says_so_and_one_build_is_queued(): void
    {
        $this->seedMarketSnapshot();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Tín hiệu đang được tính lần đầu', $html);
        Queue::assertPushed(BuildStockSignalsJob::class, 1);
    }

    #[Group('homePulse')]
    public function test_the_events_tab_shows_upcoming_events_with_company_links_and_flags_the_visitors_own(): void
    {
        $this->seedAll();
        $date = now()->addDays(9)->toDateString();
        CompanyProfile::create(['symbol' => 'FPT', 'synced_at' => now(), 'data' => ['events' => [['code' => 'D1', 'name' => 'Cổ tức', 'category' => 'DIVIDEND', 'title' => 'Cổ tức bằng tiền 1.000 đồng/cp', 'exright_date' => $date, 'record_date' => $date, 'public_date' => $date]]]]);
        $user = User::factory()->create();
        WatchlistItem::create(['user_id' => $user->id, 'symbol' => 'FPT']);
        $this->actingAs($user);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('company.show', 'FPT').'"', $html);
        $this->assertStringContainsString('Cổ tức bằng tiền 1.000 đồng/cp', $html);
        $this->assertStringContainsString('Giao dịch không hưởng quyền', $html);
        $this->assertStringContainsString('của bạn', $html);
        $this->assertStringContainsString('và các mã bạn theo dõi hoặc nắm giữ', $html);
    }

    #[Group('homePulse')]
    public function test_a_guest_sees_the_events_without_the_personal_note_and_an_honest_empty_state(): void
    {
        $this->seedAll();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Chưa có sự kiện sắp tới', $html);
        $this->assertStringContainsString('Đăng nhập và theo dõi các mã bạn quan tâm', $html);
        $this->assertStringNotContainsString('của bạn', $html);
    }

    #[Group('homePulse')]
    public function test_event_text_from_the_data_source_is_escaped(): void
    {
        $this->seedAll();
        $date = now()->addDays(4)->toDateString();
        CompanyProfile::create(['symbol' => 'FPT', 'synced_at' => now(), 'data' => ['events' => [['code' => 'X', 'category' => 'DIVIDEND', 'title' => '<img src=x onerror=alert(1)>', 'exright_date' => $date]]]]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringNotContainsString('<img src=x onerror', $html);
    }

    // ── isolation ───────────────────────────────────────────────────────────

    #[Group('homePulse')]
    public function test_a_failing_extra_part_never_takes_the_page_down(): void
    {
        $this->seedAll();
        $pulse = Mockery::mock(MarketPulseService::class);
        $pulse->shouldReceive('build')->andThrow(new RuntimeException('boom'));
        $this->app->instance(MarketPulseService::class, $pulse);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="mkPulseCard"', $html);
        $this->assertStringContainsString('id="mkForeignCard"', $html);
        $this->assertStringContainsString('id="mkHeatStage"', $html);
    }

    #[Group('homePulse')]
    public function test_the_dashboard_service_isolates_each_part_and_merges_the_visitors_symbols(): void
    {
        $user = User::factory()->create();
        WatchlistItem::create(['user_id' => $user->id, 'symbol' => 'VCB']);
        $this->seedAll();

        $world = Mockery::mock(WorldMarketService::class);
        $world->shouldReceive('snapshot')->andThrow(new RuntimeException('boom'));
        $this->app->instance(WorldMarketService::class, $world);

        $extras = $this->app->make(HomeDashboardService::class)->build(app(MarketOverviewService::class)->overview(), $user->id);

        $this->assertNull($extras['world']);
        $this->assertNotNull($extras['pulse']);
        $this->assertNotNull($extras['sentiment']);
        $this->assertSame(200, $extras['signals']['universe']);
        $this->assertSame([], $extras['events']['events']);
    }
}
