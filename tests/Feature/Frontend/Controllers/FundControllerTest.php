<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Frontend\Services\FundService;
use App\Models\Fund;
use App\Support\FundMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * RefreshDatabase is needed: every page extends layouts.app, whose navbar queries news_categories,
 * and the catalog reads the funds table. The Python-backed paths are exercised elsewhere
 * (FundServiceTest); here funds are seeded and detail JSON is served from a pre-filled Cache.
 */
class FundControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    private function fund(string $code, array $attrs = []): Fund
    {
        return Fund::create(array_merge([
            'short_name' => $code, 'name' => "QUỸ ĐẦU TƯ {$code}", 'fund_type' => 'Quỹ cổ phiếu', 'type_code' => 'STOCK',
            'fund_owner_name' => 'CÔNG TY TNHH QUẢN LÝ QUỸ ABC', 'management_fee' => 1.5, 'nav' => 12345.67,
            'nav_change_12m' => 10.5, 'nav_update_at' => '2026-09-18', 'synced_at' => now(),
        ], $attrs));
    }

    // ── catalog ─────────────────────────────────────────────────────────────

    #[Group('fundCatalog')]
    public function test_index_lists_funds_sorted_by_one_year_return_by_default(): void
    {
        $this->fund('LOWRET', ['nav_change_12m' => -3]);
        $this->fund('TOPRET', ['nav_change_12m' => 25]);

        $response = $this->get('/funds');

        $response->assertOk();
        $response->assertSeeInOrder(['TOPRET', 'LOWRET']);
        $response->assertSee('2 quỹ mở');
        $response->assertSee('+25,00%', false);
        $response->assertSee('-3,00%', false);
    }

    #[Group('fundCatalog')]
    public function test_index_filters_by_type_and_search_term(): void
    {
        $this->fund('STK1');
        $this->fund('BND1', ['type_code' => 'BOND', 'fund_type' => 'Quỹ trái phiếu']);

        $bonds = $this->get('/funds?type=BOND');
        $bonds->assertOk()->assertSee('BND1')->assertDontSee('STK1');

        $search = $this->get('/funds?q=STK');
        $search->assertOk()->assertSee('STK1')->assertDontSee('BND1');
    }

    #[Group('fundCatalog')]
    public function test_index_survives_hostile_query_values(): void
    {
        $this->fund('STK1');

        $this->get('/funds?type=%27%3B%20DROP&sort=id%3B%20DROP%20TABLE%20funds&dir=zzz&q=%25%5C_')
            ->assertOk();
        $this->assertSame(1, Fund::count());
    }

    #[Group('fundCatalog')]
    public function test_index_shows_a_friendly_error_when_the_first_load_from_fmarket_fails(): void
    {
        // Empty table => the service tries a live load; stub it as a failure (no real Python).
        $stub = Mockery::mock(FundService::class, [app(\App\Frontend\Interfaces\FundRepositoryInterface::class)])
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $stub->shouldReceive('runListScript')->andReturn(null);
        $this->app->instance(FundService::class, $stub);

        $this->get('/funds')->assertOk()->assertSee('Chưa tải được danh sách quỹ');
    }

    // ── detail page + JSON ──────────────────────────────────────────────────

    #[Group('fundCatalog')]
    public function test_show_renders_a_fund_and_404s_for_an_unknown_code(): void
    {
        $this->fund('DCDS', ['name' => 'QUỸ ĐẦU TƯ CỔ PHIẾU DRAGON CAPITAL']);

        $this->get('/funds/DCDS')->assertOk()->assertSee('DCDS')->assertSee('12.346 ₫');   // NAV shown rounded to whole VND
        $this->get('/funds/NOPE')->assertNotFound();
        $this->get('/funds/bad%20code')->assertNotFound();   // fails the route constraint
    }

    #[Group('fundCatalog')]
    public function test_a_hyphenated_fund_code_routes_correctly(): void
    {
        $this->fund('VCBF-BCF');

        $this->get('/funds/VCBF-BCF')->assertOk()->assertSee('VCBF-BCF');
    }

    #[Group('fundCatalog')]
    public function test_detail_json_is_served_from_cache_without_python(): void
    {
        $this->fund('DCDS');
        $nav = [['2026-08-01', 100.0], ['2026-09-01', 110.0]];
        Cache::put('fund-detail:DCDS', [
            'nav' => $nav, 'top_holdings' => [['code' => 'VIC', 'industry' => 'BĐS', 'percent' => 10.5]],
            'industries' => [], 'assets' => [], 'as_of' => '2026-09-09', 'errors' => [],
            'stats' => FundMetrics::allWindows($nav),
        ], 3600);

        $this->getJson('/funds/DCDS/detail')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('top_holdings.0.code', 'VIC')
            ->assertJsonPath('stats.ALL.return_pct', 10);
    }

    #[Group('fundCatalog')]
    public function test_detail_json_for_an_unknown_fund_is_404(): void
    {
        $this->getJson('/funds/NOPE/detail')->assertNotFound()->assertJsonPath('success', false);
    }

    #[Group('fundCatalog')]
    public function test_detail_json_is_502_when_the_fetch_fails_and_the_failure_is_not_cached(): void
    {
        // NB: bind the stub BEFORE the first request in a test — the router caches the resolved
        // controller instance on the Route object, so a later re-bind would be ignored.
        $this->fund('DCDS');
        $stub = Mockery::mock(FundService::class, [app(\App\Frontend\Interfaces\FundRepositoryInterface::class)])
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $stub->shouldReceive('runDetailScript')->andReturn(null);
        $this->app->instance(FundService::class, $stub);

        $this->getJson('/funds/DCDS/detail')->assertStatus(502)->assertJsonPath('success', false);
        $this->assertFalse(Cache::has('fund-detail:DCDS'));
    }

    // ── compare ─────────────────────────────────────────────────────────────

    #[Group('fundCatalog')]
    public function test_compare_route_is_not_swallowed_by_the_fund_code_route(): void
    {
        $this->fund('AAA');
        $this->fund('BBB');

        $response = $this->get('/funds/compare?codes=AAA,BBB,NOPE');

        $response->assertOk()->assertViewIs('funds.compare');
        $this->assertSame(['AAA', 'BBB'], $response->viewData('funds')->pluck('short_name')->all());   // unknown code dropped
        $response->assertSee('"code":"AAA"', false);
    }

    #[Group('fundCatalog')]
    public function test_compare_caps_at_four_funds_and_works_with_none(): void
    {
        foreach (['A1', 'B2', 'C3', 'D4', 'E5'] as $c) {
            $this->fund($c);
        }

        $this->assertCount(4, $this->get('/funds/compare?codes=A1,B2,C3,D4,E5')->viewData('funds'));
        $this->get('/funds/compare')->assertOk();
    }
}
