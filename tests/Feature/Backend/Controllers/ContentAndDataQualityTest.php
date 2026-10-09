<?php

namespace Tests\Feature\Backend\Controllers;

use App\Backend\Services\DataQualityService;
use App\Frontend\Repositories\NewsRepository;
use App\Models\ExchangeRate;
use App\Models\HotIndustry;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\Portfolio;
use App\Models\PortfolioItem;
use App\Models\Role;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Models\StockSymbol;
use App\Models\User;
use App\Models\WatchlistItem;
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
 * Admin content tools (the featured stocks of the home page, pinning / hiding news) and the data-quality report. Real routes and DB;
 * the home page is rendered with a seeded market snapshot (no Python).
 */
class ContentAndDataQualityTest extends TestCase
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
    }

    private function as(string $role): User
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $user = tap(User::factory()->create(), fn ($u) => $u->assignRole($role))->fresh();
        $this->actingAs($user);

        return $user;
    }

    private function symbols(string ...$codes): void
    {
        foreach ($codes as $code) {
            StockSymbol::create(['symbol' => $code, 'name' => "Công ty {$code}", 'exchange' => 'HSX', 'industry' => 'Ngân hàng']);
        }
    }

    private function article(string $title, array $over = []): News
    {
        // pinned_at / is_hidden are not mass-assignable on purpose: set them the way the controller does
        $flags = array_intersect_key($over, ['pinned_at' => 1, 'is_hidden' => 1]);
        $news = News::create(array_diff_key($over, $flags) + ['title' => $title, 'description' => 'd', 'url' => 'https://example.com/'.md5($title), 'url_hash' => md5($title), 'source' => 'Test', 'published_at' => now(), 'synced_at' => now()]);
        $news->forceFill($flags)->save();

        return $news;
    }

    private function price(Stock $stock, string $date, array $over = []): StockPrice
    {
        return StockPrice::create($over + ['stock_id' => $stock->id, 'date' => $date, 'open' => 10, 'high' => 12, 'low' => 9, 'close' => 11, 'volume' => 1000]);
    }

    // ── featured stocks ─────────────────────────────────────────────────────

    #[Group('adminContent')]
    public function test_the_featured_list_defaults_to_the_three_classics_and_is_capped_at_six(): void
    {
        $this->assertSame(['FPT', 'VNM', 'ACB'], SiteSettings::featuredSymbols());

        SiteSettings::set('home.featured', ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h']);

        $this->assertSame(['A', 'B', 'C', 'D', 'E', 'F'], SiteSettings::featuredSymbols());
    }

    #[Group('adminContent')]
    public function test_the_form_accepts_commas_and_spaces_in_any_case_and_drops_repeats(): void
    {
        $this->symbols('FPT', 'VCB', 'HPG');
        $this->as(Role::ADMIN);

        $this->put('/admin/site/featured', ['symbols' => 'vcb, hpg  fpt;vcb'])->assertSessionHasNoErrors();

        $this->assertSame(['VCB', 'HPG', 'FPT'], SiteSettings::featuredSymbols());
        $this->assertDatabaseHas('activity_logs', ['description' => 'Đổi cổ phiếu nổi bật ở trang chủ']);
    }

    #[Group('adminContent')]
    public function test_unknown_too_many_empty_or_odd_symbols_are_refused_and_nothing_changes(): void
    {
        $this->symbols('FPT', 'VCB', 'HPG', 'MWG', 'VIC', 'VHM', 'GAS');
        $this->as(Role::ADMIN);

        foreach (['', '   ', 'ZZZ', 'FPT, ZZZ', 'FPT VCB HPG MWG VIC VHM GAS', 'FPT<script>', 'FPT/VCB'] as $bad) {
            $this->put('/admin/site/featured', ['symbols' => $bad])->assertSessionHasErrors('symbols');
        }
        $this->assertSame(['FPT', 'VNM', 'ACB'], SiteSettings::featuredSymbols());
    }

    #[Group('adminContent')]
    public function test_the_home_page_shows_the_chosen_stocks_straight_away(): void
    {
        HotIndustry::create(['symbol' => 'VCB', 'organ_name' => 'Vietcombank', 'icb_name3' => 'Ngân hàng']);
        ExchangeRate::create(['currency_code' => 'USD', 'currency_name' => 'US DOLLAR', 'buy_cash' => '1', 'buy_transfer' => '1', 'sell' => '1', 'date' => now()->format('Y-m-d')]);
        $this->symbols('FPT', 'VNM', 'ACB', 'HPG');
        $this->seedMarketSnapshot();

        $this->get('/')->assertOk()->assertSee('Công ty FPT')->assertDontSee('Công ty HPG');

        $this->as(Role::ADMIN);
        $this->put('/admin/site/featured', ['symbols' => 'HPG, ACB'])->assertSessionHasNoErrors();

        $this->get('/')->assertOk()->assertSee('Công ty HPG')->assertDontSee('Công ty FPT');
    }

    #[Group('adminContent')]
    public function test_only_manage_features_holders_can_change_the_featured_list(): void
    {
        $this->symbols('HPG');
        $this->as(Role::USER);

        $this->put('/admin/site/featured', ['symbols' => 'HPG'])->assertRedirect();

        $this->assertSame(['FPT', 'VNM', 'ACB'], SiteSettings::featuredSymbols());
    }

    // ── pin and hide news ───────────────────────────────────────────────────

    #[Group('adminContent')]
    public function test_a_pinned_article_leads_the_home_list_and_a_hidden_one_is_gone_from_every_public_list(): void
    {
        $old = $this->article('Bài cũ được ghim', ['published_at' => now()->subDays(3)]);
        $new = $this->article('Bài mới nhất', ['published_at' => now()]);
        $hidden = $this->article('Bài bị ẩn', ['published_at' => now()->subHour()]);
        $old->forceFill(['pinned_at' => now()])->save();
        $hidden->forceFill(['is_hidden' => true])->save();

        $repo = new NewsRepository;

        $this->assertSame(['Bài cũ được ghim', 'Bài mới nhất'], $repo->getLatest(10)->pluck('title')->all());
        $this->assertSame(['Bài mới nhất', 'Bài cũ được ghim'], $repo->paginate([], 10)->pluck('title')->all(), 'the news page is chronological and hides the hidden one');
    }

    #[Group('adminContent')]
    public function test_pin_and_unpin_toggle_and_are_audited_and_clear_the_home_news_cache(): void
    {
        $item = $this->article('Tin A');
        $admin = $this->as(Role::ADMIN);
        Cache::put('homepage_news', 'stale', 900);

        $this->post("/admin/news/{$item->id}/pin")->assertRedirect();
        $this->assertNotNull($item->fresh()->pinned_at);
        $this->assertNull(Cache::get('homepage_news'));

        $this->post("/admin/news/{$item->id}/pin")->assertRedirect();
        $this->assertNull($item->fresh()->pinned_at);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'description' => "Ghim tin #{$item->id}"]);
        $this->assertDatabaseHas('activity_logs', ['description' => "Bỏ ghim tin #{$item->id}"]);
    }

    #[Group('adminContent')]
    public function test_hiding_unpins_and_showing_again_brings_it_back(): void
    {
        $item = $this->article('Tin B', ['pinned_at' => now()]);
        $this->as(Role::ADMIN);

        $this->post("/admin/news/{$item->id}/hide")->assertRedirect();
        $this->assertTrue($item->fresh()->is_hidden);
        $this->assertNull($item->fresh()->pinned_at, 'a hidden article is not pinned either');

        $this->post("/admin/news/{$item->id}/hide")->assertRedirect();
        $this->assertFalse($item->fresh()->is_hidden);
        $this->assertCount(1, (new NewsRepository)->getLatest(5));
    }

    #[Group('adminContent')]
    public function test_the_news_admin_page_labels_pinned_and_hidden_articles_and_still_lists_the_hidden_one(): void
    {
        $this->article('Tin ghim', ['pinned_at' => now()]);
        $this->article('Tin ẩn', ['is_hidden' => true]);
        $this->as(Role::ADMIN);

        $this->get('/admin/news')->assertOk()->assertSee('Đã ghim')->assertSee('Đã ẩn')->assertSee('Tin ẩn');
    }

    #[Group('adminContent')]
    public function test_the_news_actions_need_manage_features(): void
    {
        $item = $this->article('Tin C');
        $this->as(Role::USER);

        $this->post("/admin/news/{$item->id}/pin")->assertRedirect();
        $this->post("/admin/news/{$item->id}/hide")->assertRedirect();

        $item->refresh();
        $this->assertNull($item->pinned_at);
        $this->assertFalse($item->is_hidden);
    }

    #[Group('adminContent')]
    public function test_the_public_news_page_does_not_show_a_hidden_article(): void
    {
        NewsCategory::create(['name' => 'Chung', 'slug' => 'chung']);
        $this->article('Bài công khai');
        $this->article('Bài giấu kín', ['is_hidden' => true]);

        $this->get('/news')->assertOk()->assertSee('Bài công khai')->assertDontSee('Bài giấu kín');
    }

    // ── data quality ────────────────────────────────────────────────────────

    #[Group('adminDataQuality')]
    public function test_a_clean_database_reports_nothing(): void
    {
        $r = app(DataQualityService::class)->report();

        $this->assertSame([0, 0, 0, 0], array_column($r['checks'], 'count'));
    }

    #[Group('adminDataQuality')]
    public function test_a_stock_without_any_price_is_found(): void
    {
        Stock::create(['symbol' => 'EMPTY']);
        $ok = Stock::create(['symbol' => 'FINE']);
        $this->price($ok, now()->toDateString());

        $check = collect(app(DataQualityService::class)->report()['checks'])->firstWhere('key', 'no-prices');

        $this->assertSame(1, $check['count']);
        $this->assertSame(['EMPTY'], $check['sample']);
    }

    #[Group('adminDataQuality')]
    public function test_a_stock_whose_newest_price_is_old_is_stale_but_a_recent_one_is_not(): void
    {
        $old = Stock::create(['symbol' => 'OLD']);
        $this->price($old, now()->subDays(40)->toDateString());
        $this->price($old, now()->subDays(60)->toDateString());
        $fresh = Stock::create(['symbol' => 'NEW']);
        $this->price($fresh, now()->subDays(3)->toDateString());

        $check = collect(app(DataQualityService::class)->report()['checks'])->firstWhere('key', 'stale');

        $this->assertSame(1, $check['count']);
        $this->assertStringContainsString('OLD', $check['sample'][0]);
    }

    #[Group('adminDataQuality')]
    public function test_impossible_price_rows_of_the_last_thirty_days_are_found_and_old_ones_are_ignored(): void
    {
        $s = Stock::create(['symbol' => 'BAD']);
        $this->price($s, now()->subDays(2)->toDateString(), ['close' => 0]);
        $this->price($s, now()->subDays(3)->toDateString(), ['high' => 5, 'low' => 9]);
        $this->price($s, now()->subDays(4)->toDateString());   // fine
        $this->price($s, now()->subDays(90)->toDateString(), ['close' => -1]);   // outside the window

        $check = collect(app(DataQualityService::class)->report()['checks'])->firstWhere('key', 'invalid');

        $this->assertSame(2, $check['count']);
    }

    #[Group('adminDataQuality')]
    public function test_symbols_people_follow_or_hold_that_the_system_does_not_know_are_listed_once(): void
    {
        $this->symbols('FPT');
        $user = User::factory()->create();
        WatchlistItem::create(['user_id' => $user->id, 'symbol' => 'FPT']);
        WatchlistItem::create(['user_id' => $user->id, 'symbol' => 'GHOST']);
        $pf = Portfolio::create(['user_id' => $user->id, 'name' => 'P', 'total_invested' => 1, 'current_value' => 1, 'is_active' => true]);
        PortfolioItem::create(['portfolio_id' => $pf->id, 'stock_symbol' => 'GHOST', 'stock_name' => 'g', 'quantity' => 1, 'buy_price' => 1, 'current_price' => 1, 'buy_date' => now()->toDateString()]);
        PortfolioItem::create(['portfolio_id' => $pf->id, 'stock_symbol' => 'PHANTOM', 'stock_name' => 'p', 'quantity' => 1, 'buy_price' => 1, 'current_price' => 1, 'buy_date' => now()->toDateString()]);

        $check = collect(app(DataQualityService::class)->report()['checks'])->firstWhere('key', 'unknown-symbols');

        $this->assertSame(['GHOST', 'PHANTOM'], $check['sample']);
        $this->assertSame(2, $check['count']);
    }

    #[Group('adminDataQuality')]
    public function test_the_report_is_cached_and_the_button_recomputes_it(): void
    {
        $service = app(DataQualityService::class);
        $first = $service->report();
        Stock::create(['symbol' => 'LATE']);

        $this->assertSame($first['generated_at'], $service->report()['generated_at'], 'served from the cache');
        $this->assertSame(0, collect($service->report()['checks'])->firstWhere('key', 'no-prices')['count']);

        $this->as(Role::ADMIN);
        $this->post('/admin/data-quality/refresh')->assertRedirect(route('admin.data-quality'));

        $this->assertSame(1, collect($service->report()['checks'])->firstWhere('key', 'no-prices')['count']);
    }

    #[Group('adminDataQuality')]
    public function test_the_page_summarises_the_problems_and_needs_manage_features(): void
    {
        Stock::create(['symbol' => 'EMPTY']);
        $this->as(Role::ADMIN);

        $this->get('/admin/data-quality')->assertOk()->assertSee('Chất lượng dữ liệu')->assertSee('kiểm tra có vấn đề')->assertSee('EMPTY');

        $this->as(Role::USER);
        $this->get('/admin/data-quality')->assertRedirect();
        $this->post('/admin/data-quality/refresh')->assertRedirect();
    }
}
