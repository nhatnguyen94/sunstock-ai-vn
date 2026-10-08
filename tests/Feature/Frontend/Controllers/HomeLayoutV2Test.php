<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Frontend\Services\PortfolioService;
use App\Models\ExchangeRate;
use App\Models\HotIndustry;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\Portfolio;
use App\Models\PortfolioItem;
use App\Models\StockSymbol;
use App\Models\User;
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
 * The second home-page layout pass: market lists in tabs, the heat map and the VN-Index chart in one card, secondary lists in one
 * "Khám phá thêm" card, and "Của tôi" first for a signed-in visitor. Real routes and DB; the snapshot is seeded (no Python).
 */
class HomeLayoutV2Test extends TestCase
{
    use BuildsMarketPayload, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        Cache::flush();
        HotIndustry::create(['symbol' => 'VCB', 'organ_name' => 'Vietcombank', 'icb_name3' => 'Ngân hàng']);
        $this->rate('USD', 'US DOLLAR', '25720', '26100');
        StockSymbol::create(['symbol' => 'FPT', 'name' => 'FPT Corp', 'exchange' => 'HSX', 'industry' => 'Công nghệ và thông tin']);
        $this->seedMarketSnapshot(['quotes' => [
            'FPT' => [71700, 74300, -3.5, 15_500_700, 1_129_620_000_000, 79500, 69100, 74500, 74800, 71700, 'HOSE'],
        ]]);
    }

    private function rate(string $code, string $name, string $buy, string $sell): void
    {
        ExchangeRate::create(['currency_code' => $code, 'currency_name' => $name, 'buy_cash' => $buy, 'buy_transfer' => $buy, 'sell' => $sell, 'date' => now()->format('Y-m-d')]);
    }

    private function dom(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($doc);
    }

    // ── tabs ────────────────────────────────────────────────────────────────

    #[Group('homeLayoutV2')]
    public function test_every_tab_controls_an_existing_panel_and_each_tablist_has_exactly_one_selected_tab(): void
    {
        $xp = $this->dom($this->get('/')->assertOk()->getContent());

        foreach (['mkViewTabs', 'mkSideTabs', 'exploreTabs'] as $listId) {
            $tabs = $xp->query("//*[@id='{$listId}']//*[@role='tab']");
            $this->assertGreaterThanOrEqual(2, $tabs->length, "{$listId} has tabs");

            // several tabs may control one panel (Tăng / Giảm / GTGD share the movers list): a panel is shown while any of its tabs is selected
            $shown = [];
            foreach ($tabs as $tab) {
                if ($tab->getAttribute('aria-selected') === 'true') {
                    $shown[] = $tab->getAttribute('aria-controls');
                }
            }
            $selected = 0;
            foreach ($tabs as $tab) {
                $panel = $xp->query("//*[@id='".$tab->getAttribute('aria-controls')."']")->item(0);
                $this->assertNotNull($panel, "{$listId}: ".$tab->getAttribute('id').' controls a panel that exists');
                $this->assertSame('tabpanel', $panel->getAttribute('role'));

                $on = $tab->getAttribute('aria-selected') === 'true';
                $selected += $on ? 1 : 0;
                $this->assertSame($on ? '0' : '-1', $tab->getAttribute('tabindex') ?: ($on ? '0' : '-1'), 'roving tabindex');
                $this->assertSame(in_array($tab->getAttribute('aria-controls'), $shown, true), ! $panel->hasAttribute('hidden'), "{$listId}: only the panel of the selected tab is shown");
            }
            $this->assertSame(1, $selected, "{$listId}: one selected tab");
        }
    }

    #[Group('homeLayoutV2')]
    public function test_the_side_card_has_four_tabs_and_the_breadth_has_a_card_of_its_own(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $xp = $this->dom($html);

        $this->assertSame(['gainers', 'losers', 'value', 'watch'], array_map(fn ($n) => $n->getAttribute('data-tab'), iterator_to_array($xp->query("//*[@id='mkSideTabs']//*[@role='tab']"))));
        $this->assertSame(5, $xp->query("//table[contains(@class,'mk-table-side')]//th")->length, 'star, symbol, price, change, traded value');
        $this->assertStringNotContainsString('KLGD', $html);
        $this->assertStringContainsString('id="mkMovers"', $html);
        $this->assertSame(0, $xp->query("//*[@id='mkPanelBreadth']")->length, 'no breadth tab any more');
        $this->assertSame(1, $xp->query("//*[@id='mkBreadthCard']//*[@id='mkAdv']")->length);
        $this->assertStringContainsString('id="mkAdv"', $html, 'the breadth numbers the poll updates are still there');
    }

    #[Group('homeLayoutV2')]
    public function test_the_heat_map_and_the_vn_index_chart_share_one_card_with_the_map_shown_first(): void
    {
        $xp = $this->dom($this->get('/')->assertOk()->getContent());

        $this->assertSame(1, $xp->query("//*[@id='mkHeat']//*[@id='mkPanelMap']")->length);
        $this->assertSame(1, $xp->query("//*[@id='mkHeat']//*[@id='mkPanelChart']//*[@id='mkChart']")->length);
        $this->assertFalse($xp->query("//*[@id='mkPanelMap']")->item(0)->hasAttribute('hidden'));
        $this->assertTrue($xp->query("//*[@id='mkPanelChart']")->item(0)->hasAttribute('hidden'));
        $this->assertSame(1, $xp->query("//*[@id='mkHeat']//*[@id='mkHeatTools']")->length, 'exchange chips belong to the map view');
    }

    #[Group('homeLayoutV2')]
    public function test_without_heat_map_data_the_chart_is_the_only_view_and_nothing_folds(): void
    {
        Cache::flush();
        $this->seedMarketSnapshot(['quotes' => [], 'trade_date' => '2026-09-19']);

        $html = $this->get('/')->assertOk()->getContent();
        $xp = $this->dom($html);

        $this->assertStringNotContainsString('id="mkTabMap"', $html);
        $this->assertStringNotContainsString('id="mkHeatToggle"', $html);
        $this->assertStringNotContainsString('id="mkHeatStage"', $html);
        $this->assertSame('true', $xp->query("//*[@id='mkTabChart']")->item(0)->getAttribute('aria-selected'));
        $this->assertFalse($xp->query("//*[@id='mkPanelChart']")->item(0)->hasAttribute('hidden'));
    }

    #[Group('homeLayoutV2')]
    public function test_the_brief_starts_folded_with_an_accessible_toggle_and_a_remembered_choice(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('class="mk-card mk-brief is-collapsed" id="mkBrief"', $html);
        $this->assertStringContainsString('id="mkBriefToggle" aria-expanded="false" aria-controls="mkBriefMore"', $html);
        $this->assertStringContainsString('id="mkBriefMore"', $html);
        $this->assertStringContainsString("localStorage.getItem('sunstock-brief') === 'open'", $html);
        $this->assertStringContainsString('id="mkBriefHeadline"', $html, 'the headline is outside the folding part');
        $this->assertLessThan(strpos($html, 'id="mkBriefMore"'), strpos($html, 'id="mkBriefHeadline"'));
    }

    // ── Explore ─────────────────────────────────────────────────────────────

    #[Group('homeLayoutV2')]
    public function test_the_secondary_lists_live_in_one_explore_card_with_featured_stocks_open_first(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $xp = $this->dom($html);

        $this->assertSame(1, $xp->query("//*[@id='explore']")->length);
        foreach (['exPanelFeatured', 'exPanelHot', 'exPanelRates'] as $id) {
            $this->assertSame(1, $xp->query("//*[@id='explore']//*[@id='{$id}']")->length, "{$id} is inside the card");
        }
        $this->assertFalse($xp->query("//*[@id='exPanelFeatured']")->item(0)->hasAttribute('hidden'));
        $this->assertTrue($xp->query("//*[@id='exPanelHot']")->item(0)->hasAttribute('hidden'));
        $this->assertSame(1, substr_count($html, 'id="hot-industries-section"'), 'the old anchor survives for in-page links');
        $this->assertStringNotContainsString('class="featured-section"', $html);
    }

    #[Group('homeLayoutV2')]
    public function test_asking_for_a_page_of_the_hot_industries_opens_that_tab(): void
    {
        foreach (range(1, 12) as $i) {
            HotIndustry::create(['symbol' => 'H'.$i.'X', 'organ_name' => 'Co '.$i, 'icb_name3' => 'Ngành']);   // 13 rows: a second page exists
        }

        $html = $this->get('/?page=2')->assertOk()->getContent();
        $xp = $this->dom($html);

        $this->assertFalse($xp->query("//*[@id='exPanelHot']")->item(0)->hasAttribute('hidden'));
        $this->assertTrue($xp->query("//*[@id='exPanelFeatured']")->item(0)->hasAttribute('hidden'));
        $this->assertSame('true', $xp->query("//*[@id='exTabHot']")->item(0)->getAttribute('aria-selected'));
    }

    #[Group('homeLayoutV2')]
    public function test_exchange_rates_show_the_main_currencies_in_a_fixed_order_with_a_link_to_the_rest(): void
    {
        $this->rate('EUR', 'EURO', '28554', '29759');
        $this->rate('JPY', 'JAPANESE YEN', '159', '168');
        $this->rate('XYZ', 'ODD CURRENCY', '1', '2');
        $this->rate('AUD', 'AUSTRALIAN DOLLAR', '17749', '18317');

        $html = $this->get('/')->assertOk()->getContent();
        $xp = $this->dom($html);

        $codes = array_map(fn ($n) => trim($n->textContent), iterator_to_array($xp->query("//*[@id='exPanelRates']//*[contains(@class,'currency-code')]")));
        $this->assertSame(['USD', 'EUR', 'JPY', 'AUD'], $codes, 'the main currencies in a fixed order, the odd one left to the full page');
        $this->assertStringContainsString('Xem tất cả 5 ngoại tệ', $html);
        $this->assertStringContainsString('href="'.route('exchange-rate.index').'"', $html);
        $this->assertStringContainsString('25,720.00', $html);
    }

    #[Group('homeLayoutV2')]
    public function test_when_no_main_currency_is_present_the_first_six_are_shown(): void
    {
        ExchangeRate::query()->delete();
        foreach (range(1, 8) as $i) {
            $this->rate('C'.$i.'X', 'Currency '.$i, (string) ($i * 100), (string) ($i * 100 + 5));
        }

        $xp = $this->dom($this->get('/')->assertOk()->getContent());

        $this->assertSame(6, $xp->query("//*[@id='exPanelRates']//tbody/tr")->length);
    }

    // ── news ────────────────────────────────────────────────────────────────

    #[Group('homeLayoutV2')]
    public function test_the_home_page_shows_three_news_cards(): void
    {
        $cat = NewsCategory::first();   // the migration seeds the categories
        foreach (range(1, 6) as $i) {
            News::create(['title' => "Tin số {$i}", 'description' => 'Mô tả', 'url' => "https://example.test/{$i}", 'url_hash' => md5((string) $i), 'source' => 'Test', 'image_url' => null, 'category_id' => $cat->id, 'published_at' => now()->subMinutes($i), 'synced_at' => now()]);
        }

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, '<article class="news-card"'));
        $this->assertStringContainsString('news-grid news-grid-home', $html);
        $this->assertStringContainsString('Xem tất cả tin tức', $html);
    }

    // ── "Của tôi" ───────────────────────────────────────────────────────────

    #[Group('homeLayoutV2')]
    public function test_a_guest_gets_no_personal_card_and_the_plain_background(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="mkMine"', $html);
        $this->assertStringNotContainsString('has-mine', $html);
        $this->assertStringNotContainsString('id="mkWatchBody"', $html, 'the guest sees the login prompt in the watch tab');
    }

    #[Group('homeLayoutV2')]
    public function test_a_signed_in_user_without_a_portfolio_is_invited_to_create_one(): void
    {
        $this->actingAs(User::factory()->create());

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="mkMine"', $html);
        $this->assertStringContainsString('Bạn chưa có danh mục nào.', $html);
        $this->assertStringContainsString('Tạo danh mục', $html);
        $this->assertStringContainsString('id="mkMineWatch"', $html);
        $this->assertMatchesRegularExpression('/class="home-body has-mine"/', $html);
        $this->assertStringContainsString('id="mkWatchBody"', $html);
    }

    #[Group('homeLayoutV2')]
    public function test_the_personal_card_comes_before_the_market_head_and_shows_value_profit_and_the_day(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $p = Portfolio::create(['user_id' => $user->id, 'name' => 'Dài hạn', 'total_invested' => 0, 'current_value' => 0, 'is_active' => true]);
        PortfolioItem::create(['portfolio_id' => $p->id, 'stock_symbol' => 'FPT', 'stock_name' => 'FPT', 'quantity' => 100, 'buy_price' => 60000, 'current_price' => 70000, 'previous_price' => 69000, 'buy_date' => '2026-06-01']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'class="mk-head"'), strpos($html, 'id="mkMine"'), '"Của tôi" first');
        $this->assertStringContainsString('7 triệu ₫', $html);                   // 100 x 70.000
        $this->assertStringContainsString('Lãi/lỗ +1 triệu ₫ (+16,67%)', $html);  // (70.000 - 60.000) x 100
        $this->assertStringContainsString('Hôm nay +100.000 ₫ (+1,45%)', $html);    // 100 x (70.000 - 69.000)
        $this->assertMatchesRegularExpression('/mk-chip up">Lãi\/lỗ/', $html);
    }

    #[Group('homeLayoutV2')]
    public function test_the_personal_card_never_takes_the_page_down(): void
    {
        $this->actingAs(User::factory()->create());
        $stub = Mockery::mock(PortfolioService::class);
        $stub->shouldReceive('homeSummary')->andThrow(new RuntimeException('boom'));
        $this->app->instance(PortfolioService::class, $stub);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="mkMine"', $html);
        $this->assertStringContainsString('id="mkHeatStage"', $html);
    }

    #[Group('homeLayoutV2')]
    public function test_another_users_portfolio_never_appears_on_my_card(): void
    {
        $mine = User::factory()->create();
        $other = User::factory()->create();
        $p = Portfolio::create(['user_id' => $other->id, 'name' => 'Của người khác', 'total_invested' => 0, 'current_value' => 0, 'is_active' => true]);
        PortfolioItem::create(['portfolio_id' => $p->id, 'stock_symbol' => 'FPT', 'stock_name' => 'FPT', 'quantity' => 1000, 'buy_price' => 60000, 'current_price' => 70000, 'buy_date' => '2026-06-01']);
        $this->actingAs($mine);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Bạn chưa có danh mục nào.', $html);
        $this->assertStringNotContainsString('70 triệu', $html);
    }

    // ── homeSummary ─────────────────────────────────────────────────────────

    private function summary(User $u): array
    {
        return $this->app->make(PortfolioService::class)->homeSummary($u->id);
    }

    private function holding(Portfolio $p, string $symbol, array $over = []): PortfolioItem
    {
        return PortfolioItem::create(array_merge(['portfolio_id' => $p->id, 'stock_symbol' => $symbol, 'stock_name' => $symbol, 'quantity' => 100, 'buy_price' => 50000, 'current_price' => 55000, 'buy_date' => '2026-06-01'], $over));
    }

    private function portfolio(User $u, bool $active = true): Portfolio
    {
        return Portfolio::create(['user_id' => $u->id, 'name' => 'P', 'total_invested' => 0, 'current_value' => 0, 'is_active' => $active]);
    }

    #[Group('homeLayoutV2')]
    public function test_the_summary_adds_active_portfolios_from_the_stored_prices(): void
    {
        $u = User::factory()->create();
        $a = $this->portfolio($u);
        $b = $this->portfolio($u);
        $this->holding($a, 'AAA');                                                              // 5.5 tr value, 5.0 tr cost
        $this->holding($b, 'BBB', ['quantity' => 200, 'buy_price' => 30000, 'current_price' => 27000]);   // 5.4 tr value, 6.0 tr cost

        $s = $this->summary($u);

        $this->assertSame(2, $s['count']);
        $this->assertSame(2, $s['holdings']);
        $this->assertEqualsWithDelta(10_900_000, $s['value'], 0.001);
        $this->assertEqualsWithDelta(11_000_000, $s['invested'], 0.001);
        $this->assertEqualsWithDelta(-100_000, $s['pnl'], 0.001);
        $this->assertEqualsWithDelta(-0.909, $s['pnl_percent'], 0.001);
    }

    #[Group('homeLayoutV2')]
    public function test_the_day_move_uses_the_previous_price_and_is_zero_without_one(): void
    {
        $u = User::factory()->create();
        $p = $this->portfolio($u);
        $this->holding($p, 'AAA', ['previous_price' => 54000]);   // +1.000 x 100
        $this->holding($p, 'BBB');                                // no previous price: no move

        $s = $this->summary($u);

        $this->assertEqualsWithDelta(100_000, $s['day'], 0.001);
        $this->assertEqualsWithDelta(100_000 / (11_000_000 - 100_000) * 100, $s['day_percent'], 0.001);
    }

    #[Group('homeLayoutV2')]
    public function test_a_holding_with_no_price_is_counted_apart_and_never_looks_like_a_total_loss(): void
    {
        $u = User::factory()->create();
        $p = $this->portfolio($u);
        $this->holding($p, 'AAA');
        $this->holding($p, 'NOPRICE', ['current_price' => 0]);

        $s = $this->summary($u);

        $this->assertSame(1, $s['holdings']);
        $this->assertSame(1, $s['unpriced']);
        $this->assertEqualsWithDelta(5_500_000, $s['value'], 0.001);
        $this->assertEqualsWithDelta(5_000_000, $s['invested'], 0.001, 'the unpriced holding does not add its cost');
        $this->assertGreaterThan(0, $s['pnl']);
    }

    #[Group('homeLayoutV2')]
    public function test_inactive_portfolios_and_other_users_are_left_out(): void
    {
        $u = User::factory()->create();
        $other = User::factory()->create();
        $this->holding($this->portfolio($u, false), 'OFF');
        $this->holding($this->portfolio($other), 'THEIRS');

        $s = $this->summary($u);

        $this->assertSame(0, $s['count']);
        $this->assertSame(0, $s['holdings']);
        $this->assertSame(0.0, $s['value']);
        $this->assertNull($s['pnl_percent']);
        $this->assertNull($s['day_percent']);
    }

    // ── one scrolling page in four sections ─────────────────────────────────

    #[Group('homeLayoutV2')]
    public function test_the_page_is_one_scroll_with_a_jump_bar_and_nothing_hidden_behind_tabs(): void
    {
        $xp = $this->dom($this->get('/')->assertOk()->getContent());

        $links = $xp->query("//nav[@id='homeJump']//a");
        $this->assertSame(['hpOverview', 'hpMarkets', 'hpSignals', 'hpExplore', 'homeNews'], array_map(fn ($a) => $a->getAttribute('data-sec'), iterator_to_array($links)));
        foreach ($links as $a) {
            $this->assertSame(1, $xp->query("//*[@id='".$a->getAttribute('data-sec')."']")->length, 'every jump link has a target');
        }
        $this->assertSame(0, $xp->query("//*[@id='homeTabs']")->length, 'the home tabs are gone');
        foreach (['hpOverview', 'hpMarkets', 'hpSignals', 'hpExplore'] as $id) {
            $this->assertFalse($xp->query("//*[@id='{$id}']")->item(0)->hasAttribute('hidden'), "#{$id} is always visible");
        }
    }

    #[Group('homeLayoutV2')]
    public function test_the_sections_follow_one_another_and_each_block_lives_in_its_own_section(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $xp = $this->dom($html);

        $order = array_map(fn ($id) => strpos($html, "id=\"{$id}\""), ['hpOverview', 'hpMarkets', 'hpSignals', 'hpExplore', 'homeNews']);
        $sorted = $order;
        sort($sorted);
        $this->assertSame($sorted, $order, 'overview, markets, signals, explore, news');

        $where = [
            'hpOverview' => ['mkHeat', 'mkChart', 'mkBrief'],
            'hpMarkets' => ['aiPredictBtn', 'mkSide', 'mkBreadthCard', 'mkPulse'],
            'hpSignals' => ['signals', 'events'],
            'hpExplore' => ['explore', 'exPanelFeatured', 'exPanelRates'],
        ];
        foreach ($where as $section => $ids) {
            foreach ($ids as $id) {
                $this->assertSame(1, $xp->query("//*[@id='{$section}']//*[@id='{$id}']")->length, "#{$id} is inside #{$section}");
            }
        }
    }

    #[Group('homeLayoutV2')]
    public function test_the_ai_prediction_is_a_banner_with_the_ids_the_script_uses(): void
    {
        $xp = $this->dom($this->get('/')->assertOk()->getContent());

        $this->assertSame(1, $xp->query("//*[contains(@class,'ai-hero')]//button[@id='aiPredictBtn']")->length);
        foreach (['aiPredictResult', 'aiPredictLoading', 'aiPredictContent'] as $id) {
            $this->assertSame(1, $xp->query("//*[contains(@class,'ai-hero')]//*[@id='{$id}']")->length, "#{$id} is in the banner");
        }
    }

    #[Group('homeLayoutV2')]
    public function test_a_page_of_the_hot_industries_list_opens_that_list(): void
    {
        foreach (range(1, 12) as $i) {   // the list is paged by ten, so a second page needs more than ten rows
            HotIndustry::create(['symbol' => 'T'.$i, 'organ_name' => 'Company '.$i, 'icb_name3' => 'Ngành '.$i]);
        }
        Cache::flush();

        $xp = $this->dom($this->get('/?page=2')->assertOk()->getContent());

        $this->assertSame('true', $xp->query("//*[@id='exTabHot']")->item(0)->getAttribute('aria-selected'));
        $this->assertFalse($xp->query("//*[@id='exPanelHot']")->item(0)->hasAttribute('hidden'));
    }

    #[Group('homeLayoutV2')]
    public function test_the_signup_banner_closes_the_page_for_guests_and_a_signed_in_visitor_sees_none_of_it(): void
    {
        $guest = $this->dom($this->get('/')->assertOk()->getContent());
        $this->assertGreaterThanOrEqual(1, $guest->query("//*[@id='homeNews']//a[contains(@href,'register')]")->length, 'the signup banner is at the end of the page');

        $this->actingAs(User::factory()->create());
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('Quản lý danh mục đầu tư', $html);
        $this->assertStringNotContainsString('Tại sao chọn', $html);
    }

    #[Group('homeLayoutV2')]
    public function test_the_page_script_starts_the_visual_effects_and_the_pulse_sweeps_in_when_seen(): void
    {
        $index = file_get_contents(base_path('resources/frontend/js/index.js'));
        $this->assertStringContainsString("initJumpBar(document.getElementById('homeJump'))", $index);
        $this->assertStringContainsString('initTilt(', $index);
        $this->assertStringContainsString('initReveal(', $index);

        $home = file_get_contents(base_path('resources/frontend/js/market/home.js'));
        $this->assertStringContainsString('new IntersectionObserver', $home);
        $this->assertStringContainsString("'mkPulse'", $home);
    }
}
