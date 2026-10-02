<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Models\ExchangeRate;
use App\Models\HotIndustry;
use App\Models\StockSymbol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\BuildsMarketPayload;
use Tests\TestCase;

/**
 * The phone bottom navigation rendered by layouts/app.blade.php: who sees which items, which one is marked as the
 * current page, and that the stylesheet keeps it to phones and clear of the floating buttons.
 */
class MobileNavTest extends TestCase
{
    use BuildsMarketPayload, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        Cache::flush();
        HotIndustry::create(['symbol' => 'VCB', 'organ_name' => 'Vietcombank', 'icb_name3' => 'Ngân hàng']);
        ExchangeRate::create(['currency_code' => 'USD', 'currency_name' => 'US DOLLAR', 'buy_cash' => '25000', 'buy_transfer' => '25050', 'sell' => '25400', 'date' => now()->format('Y-m-d')]);
        StockSymbol::create(['symbol' => 'FPT', 'name' => 'FPT Corp', 'exchange' => 'HSX']);
        $this->seedMarketSnapshot();
    }

    /** The markup of the bottom bar only. */
    private function nav(string $html): string
    {
        $this->assertSame(1, preg_match('#<nav class="mnav d-md-none" id="mobileNav".*?</nav>#s', $html, $m), 'the bottom navigation is rendered once');

        return $m[0];
    }

    /** @return array<string,string> label => 'active'|'' */
    private function items(string $nav): array
    {
        preg_match_all('#<a class="mnav-item (is-active)?\s*" href="[^"]*"[^>]*>.*?<span>([^<]+)</span>#s', $nav, $m, PREG_SET_ORDER);

        return collect($m)->mapWithKeys(fn ($row) => [$row[2] => $row[1] === 'is-active' ? 'active' : ''])->all();
    }

    #[Group('mobileNav')]
    public function test_a_guest_gets_five_items_with_login_instead_of_account(): void
    {
        $nav = $this->nav($this->get('/')->assertOk()->getContent());

        $items = $this->items($nav);
        $this->assertSame(['Trang chủ', 'Cổ phiếu', 'Theo dõi', 'Danh mục', 'Đăng nhập'], array_keys($items));
        $this->assertStringContainsString('href="'.route('login').'"', $nav);
        $this->assertStringNotContainsString(route('profile.show'), $nav);
    }

    #[Group('mobileNav')]
    public function test_a_signed_in_user_gets_the_account_item(): void
    {
        $this->actingAs(User::factory()->create());

        $nav = $this->nav($this->get('/')->assertOk()->getContent());

        $this->assertSame(['Trang chủ', 'Cổ phiếu', 'Theo dõi', 'Danh mục', 'Tài khoản'], array_keys($this->items($nav)));
        $this->assertStringContainsString('href="'.route('profile.show').'"', $nav);
        $this->assertStringNotContainsString('Đăng nhập', $nav);
    }

    #[Group('mobileNav')]
    public function test_the_current_page_is_the_only_item_marked_active(): void
    {
        $user = User::factory()->create();
        $cases = [
            ['/', 'Trang chủ'],
            ['/watchlist', 'Theo dõi'],
            ['/portfolio', 'Danh mục'],
            ['/profile', 'Tài khoản'],
        ];

        foreach ($cases as [$path, $label]) {
            $this->actingAs($user);
            $nav = $this->nav($this->get($path)->assertOk()->getContent());
            $active = array_keys(array_filter($this->items($nav)));

            $this->assertSame([$label], $active, "{$path} marks only '{$label}'");
            $this->assertSame(1, substr_count($nav, 'aria-current="page"'), 'screen readers hear the current page once');
        }
    }

    #[Group('mobileNav')]
    public function test_a_guest_on_the_login_page_sees_login_marked_active(): void
    {
        $nav = $this->nav($this->get('/login')->assertOk()->getContent());

        $this->assertSame(['Đăng nhập'], array_keys(array_filter($this->items($nav))));
    }

    #[Group('mobileNav')]
    public function test_the_active_item_uses_the_filled_icon(): void
    {
        $nav = $this->nav($this->get('/')->assertOk()->getContent());

        $this->assertStringContainsString('bi-house-door-fill', $nav);
        $this->assertStringNotContainsString('bi-star-fill', $nav);
        $this->assertStringContainsString('class="bi bi-star"', $nav);
    }

    #[Group('mobileNav')]
    public function test_the_bar_is_phone_only_and_every_icon_is_hidden_from_screen_readers(): void
    {
        $nav = $this->nav($this->get('/')->assertOk()->getContent());

        $this->assertStringContainsString('d-md-none', $nav);
        $this->assertStringContainsString('aria-label="Điều hướng nhanh"', $nav);
        $this->assertSame(5, substr_count($nav, 'aria-hidden="true"'));
    }

    #[Group('mobileNav')]
    public function test_the_stylesheet_keeps_to_phones_and_clears_the_floating_buttons(): void
    {
        $css = file_get_contents(resource_path('frontend/css/shared/mobile-nav.css'));

        $this->assertStringContainsString('@media (max-width: 767.98px)', $css);
        $this->assertStringContainsString('env(safe-area-inset-bottom', $css, 'notch / home-indicator safe area');
        foreach (['#aiChatBubble', '#backToTop', '.fab-container', '.fd-compare-bar', '.custom-footer'] as $selector) {
            $this->assertStringContainsString($selector, $css, "{$selector} must make room for the bar");
        }
        // motion is switched off for visitors who ask for less
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $css);
        $this->assertStringContainsString('prefers-reduced-motion: no-preference', $css);
    }

    #[Group('mobileNav')]
    public function test_the_layout_loads_the_stylesheet_and_the_script_wires_the_bar(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $js = file_get_contents(resource_path('frontend/js/layouts/app.js'));

        $this->assertStringContainsString('css/shared/mobile-nav.css', $layout);
        $this->assertStringContainsString("@include('partials.mobile-nav')", $layout);
        $this->assertStringContainsString('initMobileNav()', $js);
    }
}
