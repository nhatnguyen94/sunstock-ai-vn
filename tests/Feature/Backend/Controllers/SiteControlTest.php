<?php

namespace Tests\Feature\Backend\Controllers;

use App\Frontend\Services\StockSignalService;
use App\Frontend\Services\WorldMarketService;
use App\Models\ExchangeRate;
use App\Models\HotIndustry;
use App\Models\Role;
use App\Models\StockSymbol;
use App\Models\User;
use App\Support\SiteSettings;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\BuildsMarketPayload;
use Tests\TestCase;

/**
 * Admin > Giao diện & Cache: the home blocks an admin can hide, the site-wide notice and the cache buttons — and how the public
 * pages obey them. The home page is rendered for real with a seeded market snapshot (no Python).
 */
class SiteControlTest extends TestCase
{
    use BuildsMarketPayload, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        Cache::flush();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        HotIndustry::create(['symbol' => 'VCB', 'organ_name' => 'Vietcombank', 'icb_name3' => 'Ngân hàng']);
        ExchangeRate::create(['currency_code' => 'USD', 'currency_name' => 'US DOLLAR', 'buy_cash' => '25720', 'buy_transfer' => '25720', 'sell' => '26100', 'date' => now()->format('Y-m-d')]);
        StockSymbol::create(['symbol' => 'FPT', 'name' => 'FPT Corp', 'exchange' => 'HSX', 'industry' => 'Công nghệ và thông tin']);
        $this->seedMarketSnapshot(['foreign' => ['buy_value' => 2_000_000_000, 'sell_value' => 1_000_000_000, 'net_value' => 1_000_000_000, 'by_exchange' => [], 'top_buy' => [], 'top_sell' => []], 'quotes' => ['FPT' => [71700, 74300, -3.5, 15_500_700, 1_129_620_000_000, 79500, 69100, 74500, 74800, 71700, 'HOSE']]]);
        Cache::put(WorldMarketService::CACHE_KEY, ['markets' => [['code' => 'INX', 'name' => 'S&P 500', 'region' => 'US', 'close' => 7801.77, 'previous' => 7819.0, 'change' => -17.2, 'percent' => -0.22, 'date' => now()->toDateString(), 'series' => [1, 2, 3]]], 'synced_at' => now()->toIso8601String()], 3600);
        Cache::put(StockSignalService::CACHE_KEY, ['trade_date' => now()->toDateString(), 'universe' => 200, 'built_at' => now()->toIso8601String(),
            'breakout' => [], 'breakout_total' => 0, 'breakdown' => [], 'breakdown_total' => 0, 'volume' => [], 'volume_total' => 0,
            'overbought' => [], 'overbought_total' => 0, 'oversold' => [], 'oversold_total' => 0], 3600);
    }

    private function admin(): User
    {
        return tap(User::factory()->create(), fn ($u) => $u->assignRole(Role::ADMIN))->fresh();
    }

    // ── defaults and storage ────────────────────────────────────────────────

    #[Group('siteControl')]
    public function test_everything_is_on_and_there_is_no_notice_until_an_admin_says_otherwise(): void
    {
        $this->assertSame(array_keys(SiteSettings::HOME_BLOCKS), array_keys(array_filter(SiteSettings::homeBlocks())));
        $this->assertNull(SiteSettings::activeAnnouncement());
        $this->assertSame(['enabled' => true, 'daily_limit' => 0], SiteSettings::ai());
    }

    #[Group('siteControl')]
    public function test_saving_blocks_keeps_only_known_keys_and_a_missing_one_means_off(): void
    {
        SiteSettings::saveHomeBlocks(['world' => '1', 'news' => '1', 'bogus' => '1']);

        $blocks = SiteSettings::homeBlocks();
        $this->assertTrue($blocks['world']);
        $this->assertTrue($blocks['news']);
        $this->assertFalse($blocks['heatmap']);
        $this->assertArrayNotHasKey('bogus', $blocks);
    }

    // ── the home page obeys the blocks ──────────────────────────────────────

    #[Group('siteControl')]
    public function test_every_block_is_visible_by_default(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['id="mkWorld"', 'id="mkHeat"', 'id="mkBrief"', 'id="mkForeignCard"', 'id="mkSentimentCard"', 'id="signals"', 'id="events"'] as $needle) {
            $this->assertStringContainsString($needle, $html, "{$needle} is on the page");
        }
    }

    #[Group('siteControl')]
    public function test_a_hidden_block_is_gone_from_the_page_and_the_others_stay(): void
    {
        SiteSettings::saveHomeBlocks(['world' => 0, 'heatmap' => 1, 'brief' => 1, 'foreign' => 0, 'sentiment' => 0, 'pulse' => 1, 'signals' => 1, 'events' => 1, 'news' => 1]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="mkWorld"', $html);
        $this->assertStringNotContainsString('id="mkForeignCard"', $html);
        $this->assertStringNotContainsString('id="mkSentimentCard"', $html);
        $this->assertStringContainsString('id="mkHeat"', $html);
    }

    #[Group('siteControl')]
    public function test_the_heat_map_and_the_brief_can_be_hidden_separately(): void
    {
        SiteSettings::saveHomeBlocks(['world' => 1, 'heatmap' => 0, 'brief' => 1, 'foreign' => 1, 'sentiment' => 1, 'pulse' => 1, 'signals' => 1, 'events' => 1, 'news' => 1]);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('id="mkHeatStage"', $html, 'no map');
        $this->assertStringContainsString('id="mkBrief"', $html, 'the brief is still there');

        Cache::flush();
        SiteSettings::saveHomeBlocks(['world' => 1, 'heatmap' => 1, 'brief' => 0, 'foreign' => 1, 'sentiment' => 1, 'pulse' => 1, 'signals' => 1, 'events' => 1, 'news' => 1]);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('id="mkBrief"', $html);
        $this->assertStringContainsString('id="mkHeatStage"', $html);
    }

    #[Group('siteControl')]
    public function test_hiding_both_signals_and_events_removes_the_section_and_its_jump_link(): void
    {
        SiteSettings::saveHomeBlocks(['world' => 1, 'heatmap' => 1, 'brief' => 1, 'foreign' => 1, 'sentiment' => 1, 'pulse' => 1, 'signals' => 0, 'events' => 0, 'news' => 1]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="hpSignals"', $html);
        $this->assertStringNotContainsString('data-sec="hpSignals"', $html);
        $this->assertStringContainsString('id="hpExplore"', $html);
    }

    #[Group('siteControl')]
    public function test_one_of_the_two_keeps_the_section_alive(): void
    {
        SiteSettings::saveHomeBlocks(['world' => 1, 'heatmap' => 1, 'brief' => 1, 'foreign' => 1, 'sentiment' => 1, 'pulse' => 1, 'signals' => 0, 'events' => 1, 'news' => 1]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="hpSignals"', $html);
        $this->assertStringContainsString('id="events"', $html);
        $this->assertStringNotContainsString('id="signals"', $html);
    }

    #[Group('siteControl')]
    public function test_a_hidden_block_is_not_even_computed_so_no_refresh_job_is_queued_for_it(): void
    {
        Cache::forget(WorldMarketService::CACHE_KEY);
        Cache::forget(StockSignalService::CACHE_KEY);
        SiteSettings::saveHomeBlocks(['world' => 0, 'heatmap' => 1, 'brief' => 1, 'foreign' => 1, 'sentiment' => 1, 'pulse' => 1, 'signals' => 0, 'events' => 1, 'news' => 1]);

        $this->get('/')->assertOk();

        Queue::assertNothingPushed();   // with both blocks on, an empty cache would queue the two refreshes
    }

    // ── the notice ──────────────────────────────────────────────────────────

    #[Group('siteControl')]
    public function test_an_enabled_notice_appears_on_public_pages_with_its_level_and_escaped_text(): void
    {
        SiteSettings::set('announcement', ['enabled' => true, 'level' => 'danger', 'text' => 'Bảo trì <b>lúc 23h</b>', 'url' => '/gold', 'link_text' => 'Chi tiết']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('class="site-notice danger"', $html);
        $this->assertStringContainsString('Bảo trì &lt;b&gt;lúc 23h&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>lúc 23h</b>', $html);
        $this->assertStringContainsString('href="/gold"', $html);
        $this->assertStringContainsString('Chi tiết', $html);
    }

    #[Group('siteControl')]
    public function test_a_disabled_or_empty_notice_shows_nothing(): void
    {
        SiteSettings::set('announcement', ['enabled' => false, 'level' => 'info', 'text' => 'Ẩn', 'url' => '', 'link_text' => '']);
        $this->get('/')->assertOk()->assertDontSee('id="siteNotice"', false);

        SiteSettings::set('announcement', ['enabled' => true, 'level' => 'info', 'text' => '   ', 'url' => '', 'link_text' => '']);
        $this->get('/')->assertOk()->assertDontSee('id="siteNotice"', false);
    }

    // ── the admin forms ─────────────────────────────────────────────────────

    #[Group('siteControl')]
    public function test_the_blocks_form_saves_what_is_ticked_and_is_audited(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->put('/admin/site/blocks', ['blocks' => ['world' => '1', 'news' => '1']])->assertRedirect();

        $blocks = SiteSettings::homeBlocks();
        $this->assertSame(['world', 'news'], array_keys(array_filter($blocks)));
        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'description' => 'Đổi các khối hiển thị của trang chủ']);
    }

    #[Group('siteControl')]
    public function test_the_notice_form_saves_and_refuses_unsafe_links_and_an_empty_text_when_enabled(): void
    {
        $this->actingAs($this->admin());
        $form = ['enabled' => '1', 'level' => 'warning', 'text' => 'Có sự cố nhỏ', 'url' => 'https://example.com/status', 'link_text' => 'Trang trạng thái'];

        $this->put('/admin/site/announcement', $form)->assertSessionHasNoErrors();
        $this->assertSame('Có sự cố nhỏ', SiteSettings::activeAnnouncement()['text']);

        foreach (['javascript:alert(1)', 'data:text/html,x', '//evil.example', 'http://insecure.example', 'ftp://x.example', '/ho le'] as $bad) {
            $this->put('/admin/site/announcement', ['url' => $bad] + $form)->assertSessionHasErrors('url');
        }
        $this->put('/admin/site/announcement', ['level' => 'purple'] + $form)->assertSessionHasErrors('level');
        $this->put('/admin/site/announcement', ['text' => '  '] + $form)->assertSessionHasErrors('text');
        $this->put('/admin/site/announcement', ['text' => str_repeat('x', 301)] + $form)->assertSessionHasErrors('text');

        $this->assertSame('https://example.com/status', SiteSettings::announcement()['url'], 'refused forms change nothing');
    }

    #[Group('siteControl')]
    public function test_an_external_link_opens_safely(): void
    {
        SiteSettings::set('announcement', ['enabled' => true, 'level' => 'info', 'text' => 'Tin', 'url' => 'https://example.com/x', 'link_text' => '']);

        $this->get('/')->assertOk()->assertSee('rel="noopener noreferrer"', false)->assertSee('Xem thêm');
    }

    #[Group('siteControl')]
    public function test_turning_the_notice_off_keeps_its_text_for_next_time(): void
    {
        $this->actingAs($this->admin());
        $this->put('/admin/site/announcement', ['enabled' => '1', 'level' => 'info', 'text' => 'Giữ lại'])->assertSessionHasNoErrors();
        $this->put('/admin/site/announcement', ['level' => 'info', 'text' => 'Giữ lại'])->assertSessionHasNoErrors();

        $this->assertNull(SiteSettings::activeAnnouncement());
        $this->assertSame('Giữ lại', SiteSettings::announcement()['text']);
    }

    // ── cache buttons ───────────────────────────────────────────────────────

    #[Group('siteControl')]
    public function test_a_cache_button_clears_its_own_keys_and_nothing_else(): void
    {
        $this->actingAs($this->admin());
        Cache::put(SiteSettings::featuredCacheKey(), 'x', 600);
        Cache::put('homepage_news', 'x', 600);
        Cache::put('screener_ratio_metrics', 'x', 600);
        Cache::put('unrelated', 'x', 600);

        $this->post('/admin/site/cache/home')->assertRedirect();

        $this->assertNull(Cache::get(SiteSettings::featuredCacheKey()));
        $this->assertNull(Cache::get('homepage_news'));
        $this->assertSame('x', Cache::get('screener_ratio_metrics'));
        $this->assertSame('x', Cache::get('unrelated'));
        $this->assertDatabaseHas('activity_logs', ['description' => 'Xóa cache: Dữ liệu trang chủ']);
    }

    #[Group('siteControl')]
    public function test_clearing_the_weekly_prediction_cache_makes_the_next_press_ask_again(): void
    {
        $this->actingAs($this->admin());
        $key = 'ai_market_predict_v2_'.date('oW');
        Cache::put($key, 'bản cũ', 600);

        $this->post('/admin/site/cache/ai')->assertRedirect();

        $this->assertNull(Cache::get($key));
    }

    #[Group('siteControl')]
    public function test_an_unknown_cache_group_is_a_404_and_clears_nothing(): void
    {
        $this->actingAs($this->admin());
        Cache::put(SiteSettings::featuredCacheKey(), 'x', 600);

        $this->post('/admin/site/cache/everything')->assertNotFound();

        $this->assertSame('x', Cache::get(SiteSettings::featuredCacheKey()));
    }

    // ── access ──────────────────────────────────────────────────────────────

    #[Group('siteControl')]
    public function test_the_page_is_for_manage_features_holders_only(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/admin/site')->assertRedirect();
        $this->put('/admin/site/blocks', ['blocks' => []])->assertRedirect();
        $this->put('/admin/site/announcement', ['enabled' => '1', 'level' => 'info', 'text' => 'x'])->assertRedirect();
        $this->post('/admin/site/cache/home')->assertRedirect();
        $this->assertNull(SiteSettings::activeAnnouncement());

        $this->flushSession();
        $this->actingAs($this->admin());
        $this->get('/admin/site')->assertOk()->assertSee('Khối hiển thị ở trang chủ')->assertSee('Thông báo toàn trang')->assertSee('Xóa cache');
    }

    #[Group('siteControl')]
    public function test_the_admin_menu_links_the_two_new_pages(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin')->assertOk()->assertSee(route('admin.ai.index'), false)->assertSee(route('admin.site.index'), false);
    }
}
