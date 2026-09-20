<?php

namespace Tests\Feature\Backend\Controllers;

use App\Models\ActivityLog;
use App\Models\ExchangeRate;
use App\Models\GoldPrice;
use App\Models\MarketSnapshot;
use App\Models\News;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * The redesigned admin shell (layouts/admin.blade.php): navigation driven by permissions, command palette data,
 * flash messages as toasts, the failed-jobs badge, plus the new dashboard and timeline. Vite is faked: what matters is
 * the markup and the data behind it.
 */
class AdminShellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    /** @param string[] $permissions */
    private function actingAsAdminWith(array $permissions, string $role = Role::WEBADMIN): User
    {
        $r = Role::create(['name' => $role, 'display_name' => 'Vai trò ' . $role]);
        $r->permissions()->sync(collect($permissions)->map(fn ($n) => Permission::firstOrCreate(['name' => $n], ['display_name' => $n])->id));

        $user = User::factory()->create(['name' => 'Ngô Quản Trị']);
        $user->roles()->attach($r->id);
        $this->actingAs($user);

        return $user;
    }

    private function paletteItems(string $html): array
    {
        preg_match('#<script type="application/json" id="adNavData">(.*?)</script>#s', $html, $m);

        return json_decode($m[1] ?? '[]', true);
    }

    // ── shell ───────────────────────────────────────────────────────────────

    #[Group('adminShell')]
    public function test_the_sidebar_and_palette_only_offer_what_the_user_is_allowed_to_open(): void
    {
        $this->actingAsAdminWith(['manage-features']);

        $html = $this->get('/admin')->assertOk()->getContent();
        $titles = array_column($this->paletteItems($html), 'title');

        $this->assertContains('Quản lý Stock', $titles);
        $this->assertContains('Sync Status', $titles);
        $this->assertContains('Đổi mật khẩu', $titles);
        $this->assertNotContains('Quản lý Users', $titles);        // needs manage-users
        $this->assertNotContains('Giám sát Queue', $titles);
        $this->assertStringContainsString('Quản lý Stock', $html);
        $this->assertStringNotContainsString('Quản lý Users', $html);
        $this->assertStringNotContainsString('Giám sát Queue', $html);
    }

    #[Group('adminShell')]
    public function test_an_admin_with_every_permission_gets_every_group(): void
    {
        $this->actingAsAdminWith(['manage-users', 'manage-roles', 'manage-permissions', 'manage-queue', 'manage-features', 'view-timeline'], Role::ADMIN);

        $html = $this->get('/admin')->assertOk()->getContent();
        $titles = array_column($this->paletteItems($html), 'title');

        foreach (['Dashboard', 'Timeline', 'Quản lý Users', 'Vai trò', 'Quyền hạn', 'Giám sát Queue', 'Quản lý Stock', 'Quản lý News', 'Danh mục Tin tức', 'Quản lý Portfolio', 'Sync Status'] as $t) {
            $this->assertContains($t, $titles, "palette should offer {$t}");
        }
        foreach (['Tổng quan', 'Hệ thống', 'Nội dung & dữ liệu'] as $g) {
            $this->assertStringContainsString('ad-nav-label">' . htmlspecialchars($g, ENT_QUOTES) . '<', $html);
        }
    }

    #[Group('adminShell')]
    public function test_the_current_page_is_marked_active_in_the_sidebar(): void
    {
        $this->actingAsAdminWith(['manage-users']);

        $html = $this->get('/admin/users')->getContent();

        $this->assertMatchesRegularExpression('#<a class="nav-link active" href="[^"]*/admin/users"[^>]*aria-current="page"#', $html);
        $this->assertDoesNotMatchRegularExpression('#nav-link active" href="[^"]*/admin"\s#', $html);
    }

    #[Group('adminShell')]
    public function test_the_shell_has_theme_switcher_palette_toasts_and_a_confirm_dialog(): void
    {
        $this->actingAsAdminWith(['manage-features']);

        $html = $this->get('/admin')->getContent();

        $this->assertStringContainsString('data-theme-choice="dark"', $html);
        $this->assertStringContainsString('id="adPalette"', $html);
        $this->assertStringContainsString('id="adToasts"', $html);
        $this->assertStringContainsString('id="adConfirm"', $html);
        $this->assertStringContainsString("localStorage.getItem('tabler-theme')", $html);      // theme applied before first paint
        $this->assertStringContainsString('Ngô Quản Trị', $html);
    }

    #[Group('adminShell')]
    public function test_flash_messages_are_present_for_the_toast_layer_and_escaped(): void
    {
        $this->actingAsAdminWith(['manage-features']);

        $html = $this->withSession(['success' => 'Đã lưu <b>xong</b>', 'error' => 'Lỗi rồi'])->get('/admin')->getContent();

        $this->assertStringContainsString('data-flash="success" hidden>Đã lưu &lt;b&gt;xong&lt;/b&gt;</div>', $html);
        $this->assertStringContainsString('data-flash="error" hidden>Lỗi rồi</div>', $html);
    }

    #[Group('adminShell')]
    public function test_the_queue_link_shows_how_many_jobs_failed(): void
    {
        $this->actingAsAdminWith(['manage-queue']);
        foreach (range(1, 3) as $i) {
            DB::table('failed_jobs')->insert(['uuid' => "uuid-{$i}", 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'boom', 'failed_at' => now()]);
        }

        $html = $this->get('/admin')->getContent();

        $this->assertMatchesRegularExpression('#class="ad-nav-count"[^>]*>3</span>#', $html);
    }

    #[Group('adminShell')]
    public function test_no_failed_jobs_means_no_badge(): void
    {
        $this->actingAsAdminWith(['manage-queue']);

        $this->assertStringNotContainsString('class="ad-nav-count"', $this->get('/admin')->getContent());
    }

    #[Group('adminShell')]
    public function test_the_login_page_is_the_new_split_layout_and_still_a_form_post(): void
    {
        $r = $this->get('/admin/login');

        $r->assertOk();
        $r->assertSee('Chào mừng trở lại');
        $r->assertSee('name="email"', false);
        $r->assertSee('name="password"', false);
        $r->assertSee('action="' . route('admin.login') . '"', false);
        $r->assertDontSee('cdn.jsdelivr.net/npm/@tabler', false);      // bundled locally now, no CDN
    }

    // ── dashboard ───────────────────────────────────────────────────────────

    #[Group('adminShell')]
    public function test_the_dashboard_shows_kpis_and_a_status_for_every_data_source(): void
    {
        $this->actingAsAdminWith(['manage-features', 'manage-users']);
        News::query()->count();
        ExchangeRate::create(['currency_code' => 'USD', 'currency_name' => 'US DOLLAR', 'buy_cash' => '1', 'buy_transfer' => '1', 'sell' => '1', 'date' => now()->toDateString()]);
        GoldPrice::create(['source' => 'SJC', 'metal' => 'gold', 'product' => 'X', 'branch' => '', 'unit' => 'luong', 'buy_price' => 1, 'sell_price' => 2, 'quoted_at' => now(), 'synced_at' => now()]);
        MarketSnapshot::create(['trade_date' => '2026-09-18', 'data' => ['indices' => []], 'synced_at' => now()->subDays(10)]);

        $r = $this->get('/admin');

        $r->assertOk();
        foreach (['Người dùng', 'Danh mục đầu tư', 'Nguồn dữ liệu', 'Sức khỏe hệ thống', 'Giá cổ phiếu', 'Tổng quan thị trường', 'Tỷ giá ngoại tệ', 'Giá vàng', 'Tin tức', 'Hoạt động gần đây'] as $text) {
            $r->assertSee($text);
        }
        $r->assertSee('ad-dot ok', false);      // the gold sync is fresh
        $r->assertSee('ad-dot bad', false);     // the market snapshot is 10 days old
        $r->assertSee('ad-dot off', false);     // no news synced yet
    }

    #[Group('adminShell')]
    public function test_the_dashboard_counts_unverified_users_and_failed_jobs(): void
    {
        $this->actingAsAdminWith(['manage-users', 'manage-queue']);
        User::factory()->unverified()->count(2)->create();
        DB::table('failed_jobs')->insert(['uuid' => 'u1', 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'boom', 'failed_at' => now()]);

        $r = $this->get('/admin');

        $r->assertSee('2 chưa xác thực email');
        $r->assertSee('Cần xem lại hoặc thử lại');
    }

    #[Group('adminShell')]
    public function test_the_expensive_price_row_count_is_cached(): void
    {
        $this->actingAsAdminWith(['manage-features']);

        $this->get('/admin')->assertOk();

        $this->assertTrue(Cache::has('admin:stock-price-rows'));
    }

    #[Group('adminShell')]
    public function test_the_dashboard_hides_actions_the_user_cannot_use(): void
    {
        $this->actingAsAdminWith(['manage-features']);

        $html = $this->get('/admin')->getContent();

        $this->assertStringNotContainsString(route('admin.users.create'), $html);
        $this->assertStringContainsString(route('admin.sync-status'), $html);
    }

    // ── timeline ────────────────────────────────────────────────────────────

    #[Group('adminShell')]
    public function test_the_timeline_groups_by_day_and_offers_filter_chips_with_counts(): void
    {
        $this->actingAsAdminWith(['view-timeline']);
        ActivityLog::create(['user_name' => 'An', 'event_type' => 'watchlist_added', 'description' => 'Theo dõi FPT', 'created_at' => now()]);
        ActivityLog::create(['user_name' => 'An', 'event_type' => 'portfolio_trade', 'description' => 'Mua 100 FPT', 'created_at' => now()]);
        ActivityLog::create(['user_name' => 'Bình', 'event_type' => 'portfolio_trade', 'description' => 'Bán 50 HPG', 'properties' => ['symbol' => 'HPG'], 'created_at' => now()->subDays(2)]);

        $r = $this->get('/admin/timeline');

        $r->assertOk();
        $r->assertSee('Hôm nay');
        $r->assertSee('Theo dõi cổ phiếu');
        $r->assertSee('Giao dịch mua/bán');
        $r->assertSee('Mua 100 FPT');
        $r->assertSee('symbol: HPG');
        $this->assertMatchesRegularExpression('#Giao dịch mua/bán <span class="badge bg-green-lt ms-1">2</span>#u', $r->getContent());
    }

    #[Group('adminShell')]
    public function test_timeline_filter_by_type_and_unknown_event_types_still_render(): void
    {
        $this->actingAsAdminWith(['view-timeline']);
        ActivityLog::create(['user_name' => 'An', 'event_type' => 'watchlist_added', 'description' => 'Theo dõi FPT', 'created_at' => now()]);
        ActivityLog::create(['user_name' => 'An', 'event_type' => 'something_new', 'description' => 'Sự kiện lạ', 'created_at' => now()]);

        $this->get('/admin/timeline?type=watchlist_added')->assertOk()->assertSee('Theo dõi FPT')->assertDontSee('Sự kiện lạ');
        $this->get('/admin/timeline')->assertOk()->assertSee('Sự kiện lạ')->assertSee('something_new');
    }

    #[Group('adminShell')]
    public function test_timeline_is_still_gated_by_its_permission(): void
    {
        $this->actingAsAdminWith(['manage-features']);

        $this->get('/admin/timeline')->assertForbidden();
    }

    // ── pages that changed markup ───────────────────────────────────────────

    #[Group('adminShell')]
    public function test_destructive_actions_ask_through_the_confirm_dialog_not_window_confirm(): void
    {
        $this->actingAsAdminWith(['manage-users', 'manage-features']);
        User::factory()->create(['name' => 'Người Bị Xóa']);

        $html = $this->get('/admin/users')->getContent();

        $this->assertStringContainsString('data-confirm="Bạn có chắc chắn muốn xóa user Người Bị Xóa?', $html);
        $this->assertStringNotContainsString('onsubmit="return confirm', $html);
        $this->assertStringNotContainsString('onclick="return confirm', $html);
    }

    #[Group('adminShell')]
    public function test_no_admin_page_loads_assets_from_a_cdn_or_falls_back_to_window_confirm(): void
    {
        $this->actingAsAdminWith(['manage-users', 'manage-features', 'manage-roles', 'manage-permissions', 'manage-queue', 'view-timeline'], Role::ADMIN);

        foreach (['/admin', '/admin/users', '/admin/stocks', '/admin/news', '/admin/portfolios', '/admin/roles', '/admin/permissions', '/admin/news-categories', '/admin/sync-status', '/admin/queue', '/admin/timeline', '/admin/account'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('cdn.jsdelivr.net', $html, $url);
            $this->assertStringNotContainsString('window.confirm', $html, $url);
        }
    }
}
