<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Frontend\Services\MarketOverviewService;
use App\Models\ExchangeRate;
use App\Models\HotIndustry;
use App\Models\StockSymbol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\BuildsMarketPayload;
use Tests\TestCase;

/**
 * The "data first" home page: a compact hero with the search box, the market overview straight under it,
 * marketing blocks last and only for visitors who are not signed in. Real route and DB, snapshot seeded so the
 * Python boundary is never reached.
 */
class HomeLayoutTest extends TestCase
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

    private function positionOf(string $html, string $needle): int
    {
        $pos = strpos($html, $needle);
        $this->assertNotFalse($pos, "'{$needle}' is missing from the home page");

        return $pos;
    }

    #[Group('homeLayout')]
    public function test_the_search_box_lives_in_the_compact_hero_and_the_market_follows_it(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('hero-compact', $html);
        $search = $this->positionOf($html, 'id="symbol"');
        $market = $this->positionOf($html, 'id="market"');

        $this->assertLessThan($market, $search, 'search comes before the market overview');
        // nothing but the hero sits between the search box and the market overview
        $between = substr($html, $search, $market - $search);
        foreach (['id="explore"', 'aiPredictBtn', 'Tại sao chọn', 'info-section'] as $later) {
            $this->assertStringNotContainsString($later, $between, "'{$later}' must come after the market overview");
        }
    }

    #[Group('homeLayout')]
    public function test_the_hero_keeps_what_the_search_script_binds_to(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['id="symbol"', 'name="symbol"', 'class="search-form-wrapper"', 'search-btn', 'btn-text', 'id="notFoundMsg"'] as $hook) {
            $this->assertStringContainsString($hook, $html, "index.js binds to {$hook}");
        }
        $this->assertStringContainsString('href="'.url('/stock/compare').'"', $html);
        $this->assertStringContainsString('Ctrl+K', $html);
    }

    #[Group('homeLayout')]
    public function test_the_old_hero_marketing_is_gone(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['700+', 'Hoàn toàn miễn phí', 'Dữ liệu cập nhật tự động mỗi ngày'] as $old) {
            $this->assertStringNotContainsString($old, $html);
        }
    }

    #[Group('homeLayout')]
    public function test_the_working_blocks_follow_the_market_in_order(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $order = ['id="market"', 'id="aiPredictBtn"', 'id="explore"', 'id="exPanelFeatured"', 'id="exPanelHot"', 'id="exPanelRates"'];
        $last = -1;
        foreach ($order as $needle) {
            $pos = $this->positionOf($html, $needle);
            $this->assertGreaterThan($last, $pos, "'{$needle}' is out of order");
            $last = $pos;
        }
    }

    #[Group('homeLayout')]
    public function test_a_guest_gets_the_pitch_and_the_signup_banner_at_the_bottom(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $market = $this->positionOf($html, 'id="market"');
        $pitch = $this->positionOf($html, 'Tại sao chọn');
        $cta = $this->positionOf($html, 'Quản lý danh mục đầu tư');

        $this->assertGreaterThan($market, $pitch);
        $this->assertGreaterThan($pitch, $cta, 'the signup banner closes the page');
    }

    #[Group('homeLayout')]
    public function test_a_signed_in_user_is_not_sold_the_product_they_already_use(): void
    {
        $this->actingAs(User::factory()->create());

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="market"', $html);
        $this->assertStringNotContainsString('Tại sao chọn', $html);
        $this->assertStringNotContainsString('Tạo tài khoản miễn phí', $html);
        $this->assertStringContainsString('hero-compact', $html);
    }

    #[Group('homeLayout')]
    public function test_the_page_still_renders_without_market_data(): void
    {
        $stub = Mockery::mock(MarketOverviewService::class)->makePartial();
        $stub->shouldReceive('overview')->andReturn(['has_data' => false, 'error' => 'offline']);
        $this->app->instance(MarketOverviewService::class, $stub);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="symbol"', $html);          // search still there
        $this->assertStringContainsString('Chưa tải được dữ liệu thị trường', $html);
    }

    #[Group('homeLayout')]
    public function test_everything_under_the_hero_shares_one_decorated_background(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $wrapper = $this->positionOf($html, 'class="home-body');
        $this->assertGreaterThan($this->positionOf($html, 'hero-compact'), $wrapper, 'the wrapper starts under the hero');
        $this->assertLessThan($this->positionOf($html, 'id="market"'), $wrapper, 'the market section is inside the wrapper');
        $this->assertLessThan($this->positionOf($html, 'id="explore"'), $wrapper);
        $this->assertStringNotContainsString('home-lower', $html, 'one continuous background, not a second tinted zone');
        $this->assertSame(5, substr_count($html, '<i class="g'), 'five glows share the one background');
        $this->assertStringContainsString('<svg class="hero-line"', $html);
        $this->assertStringContainsString('class="home-glow" aria-hidden="true"', $html);   // decoration is hidden from screen readers
        $this->assertStringContainsString('class="hero-fx" aria-hidden="true"', $html);
        $this->assertStringContainsString('{{-- /home-body --}}', file_get_contents(resource_path('views/index.blade.php')));
        $this->assertSame(substr_count($html, '<div'), substr_count($html, '</div>'), 'balanced <div> tags');
    }

    #[Group('homeLayout')]
    public function test_the_cards_below_the_first_screen_animate_in_on_scroll(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertGreaterThanOrEqual(2, substr_count($html, 'data-aos="fade-up"'));
        $this->assertMatchesRegularExpression('/<section class="mk-card mk-explore" id="explore" data-aos="fade-up">/', $html);
    }

    #[Group('homeLayout')]
    public function test_decorative_motion_is_switched_off_for_visitors_who_ask_for_less(): void
    {
        $animated = ['home-float', 'home-drift', 'home-bar', 'hero-line', 'mk-rise', 'mk-reveal', 'mk-grow'];
        foreach (['css/index.css', 'css/market/market.css'] as $file) {
            $css = file_get_contents(resource_path('frontend/'.$file));
            $guard = strpos($css, '@media (prefers-reduced-motion: no-preference)');
            $this->assertNotFalse($guard, "{$file} guards its animations");
            // every `animation:` that uses this file's keyframes sits inside the guard (after it, before the next @keyframes)
            preg_match_all('/animation(?:-duration)?:[^;{}]*;/', $css, $m, PREG_OFFSET_CAPTURE);
            foreach ($m[0] as [$rule, $offset]) {
                if (array_filter($animated, fn ($name) => str_contains($rule, $name))) {
                    $this->assertGreaterThan($guard, $offset, "{$rule} must be inside the reduced-motion guard");
                }
            }
        }
    }
}
