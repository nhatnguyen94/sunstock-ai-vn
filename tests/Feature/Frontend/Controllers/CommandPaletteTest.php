<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * The global command palette (Ctrl+K): the server-rendered part — which pages it offers to whom, the dialog markup
 * and its accessibility hooks, the trigger buttons, and that the old per-page Ctrl+K handlers are gone.
 */
class CommandPaletteTest extends TestCase
{
    use RefreshDatabase;

    /** The pages the palette was given for this visitor. @return array<int, array<string, string>> */
    private function pages(string $path = '/login'): array
    {
        $html = $this->get($path)->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<script type="application/json" id="paletteData">(.*?)</script>#s', $html, $m), 'the palette data is on the page once');
        $this->assertStringNotContainsString('<', $m[1], 'no raw tag characters inside the data block');
        $data = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);

        return $data['pages'];
    }

    #[Group('commandPalette')]
    public function test_a_guest_is_offered_login_and_register_but_not_the_account_page(): void
    {
        $titles = array_column($this->pages(), 'title');

        $this->assertContains('Đăng nhập', $titles);
        $this->assertContains('Đăng ký tài khoản', $titles);
        $this->assertNotContains('Thông tin cá nhân', $titles);
    }

    #[Group('commandPalette')]
    public function test_a_signed_in_user_is_offered_the_account_page_instead(): void
    {
        $this->actingAs(User::factory()->create());

        $titles = array_column($this->pages('/profile'), 'title');

        $this->assertContains('Thông tin cá nhân', $titles);
        $this->assertNotContains('Đăng nhập', $titles);
        $this->assertNotContains('Đăng ký tài khoản', $titles);
    }

    #[Group('commandPalette')]
    public function test_every_visitor_gets_the_main_pages_as_site_relative_links_and_never_the_admin(): void
    {
        $pages = $this->pages();

        foreach (['Trang chủ', 'Tra cứu cổ phiếu', 'So sánh cổ phiếu', 'Stock Screener', 'Quỹ ETF', 'Quỹ mở', 'Giá vàng', 'Tỷ giá ngoại tệ', 'Tin tức', 'Danh sách theo dõi', 'Danh mục đầu tư'] as $title) {
            $this->assertContains($title, array_column($pages, 'title'), "{$title} is offered");
        }
        foreach ($pages as $page) {
            $this->assertStringStartsWith('/', $page['url'], "{$page['title']} is a site-relative link");
            $this->assertStringNotContainsString('//', $page['url']);
            $this->assertStringNotContainsString('/admin', $page['url'], 'the admin area is not advertised');
            $this->assertNotSame('', $page['icon']);
        }
    }

    #[Group('commandPalette')]
    public function test_the_dialog_is_closed_by_default_and_carries_its_accessibility_hooks(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<div class="cp" id="cmdPalette" hidden role="dialog" aria-modal="true" aria-label="[^"]+">#', $html);
        $this->assertStringContainsString('id="cpInput"', $html);
        $this->assertStringContainsString('role="combobox"', $html);
        $this->assertStringContainsString('aria-controls="cpList"', $html);
        $this->assertStringContainsString('<ul class="cp-list" id="cpList" role="listbox"', $html);
        $this->assertStringContainsString('data-cp-close', $html);
    }

    #[Group('commandPalette')]
    public function test_the_navbar_has_a_trigger_for_desktop_and_one_for_phones(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'data-palette-open'));
        $this->assertStringContainsString('nav-search-quick d-xl-none', $html);   // phones / tablets: next to the menu button
        $this->assertStringContainsString('class="nav-link nav-search"', $html);  // desktop: with the shortcut hint
        $this->assertSame(2, preg_match_all('/data-palette-open[^>]*aria-label="[^"]+"/', $html), 'both triggers are labelled');
    }

    #[Group('commandPalette')]
    public function test_the_palette_is_on_every_frontend_page(): void
    {
        foreach (['/login', '/register', '/news'] as $path) {
            $this->assertStringContainsString('id="cmdPalette"', $this->get($path)->getContent(), "{$path} has the palette");
        }
    }

    #[Group('commandPalette')]
    public function test_the_old_per_page_ctrl_k_handlers_are_gone_so_they_cannot_fight_the_palette(): void
    {
        foreach (['js/index.js', 'js/stock/stock.js'] as $file) {
            $source = file_get_contents(resource_path('frontend/'.$file));
            $this->assertDoesNotMatchRegularExpression("/e\\.key === 'k'/", $source, "{$file} still handles Ctrl+K itself");
        }
        $layoutJs = file_get_contents(resource_path('frontend/js/layouts/app.js'));
        $this->assertStringContainsString('initPalette()', $layoutJs);
        $this->assertStringContainsString('css/shared/palette.css', file_get_contents(resource_path('views/layouts/app.blade.php')));
    }

    #[Group('commandPalette')]
    public function test_results_are_built_with_text_nodes_never_html_strings(): void
    {
        $js = file_get_contents(resource_path('frontend/js/shared/palette.js'));

        // the two places that use innerHTML build it from constants only (an icon class chosen by the code, not by the visitor)
        $this->assertSame(1, substr_count($js, 'innerHTML'), 'only the icon uses innerHTML');
        $this->assertStringContainsString("'<i class=\"bi bi-' + (item.icon || 'arrow-right') + '\"></i>'", $js);
        $this->assertStringContainsString('encodeURIComponent(item.symbol)', $js, 'the symbol is encoded before it goes into a URL');
        $this->assertStringContainsString('encodeURIComponent(q.trim())', $js, 'the query is encoded before it goes into the search URL');
    }

    #[Group('commandPalette')]
    public function test_the_stylesheet_honours_reduced_motion(): void
    {
        $css = file_get_contents(resource_path('frontend/css/shared/palette.css'));

        $this->assertStringContainsString('prefers-reduced-motion: no-preference', $css);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $css);
        $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion: no-preference\) \{[^@]*animation:/', $css);
    }
}
