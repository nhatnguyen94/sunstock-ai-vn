<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Frontend\Services\CompanyProfileService;
use App\Jobs\SyncCompanyProfileJob;
use App\Models\CompanyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * RefreshDatabase: pages extend layouts.app (navbar queries news_categories) and read company_profiles.
 * The Python boundary is stubbed wherever a request could reach it.
 */
class CompanyProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    private function seedProfile(string $symbol = 'FPT', ?\DateTimeInterface $syncedAt = null, array $over = []): CompanyProfile
    {
        return CompanyProfile::create([
            'symbol' => $symbol,
            'synced_at' => $syncedAt ?? now(),
            'data' => array_replace_recursive([
                'symbol' => $symbol,
                'overview' => [
                    'name' => 'Công ty Cổ phần FPT', 'exchange' => 'HOSE', 'sector' => 'Technology',
                    'market_cap' => 122_917_204_457_400, 'current_price' => 71700, 'website' => 'https://fpt.com',
                    'rating' => 'BUY', 'employees' => 54110,
                ],
                'ownership' => [['type' => 'CĐ tổ chức', 'percent' => 44.01, 'shares' => 749771552]],
                'shareholders' => [['name' => 'Trương Gia Bình', 'percent' => 6.89, 'shares' => 117347966, 'date' => '2026-08-03']],
                'officers' => [['name' => 'Trương Gia Bình', 'position' => 'CTHĐQT', 'group' => 'board', 'since' => 1988, 'independent' => false, 'own_percent' => 6.89, 'own_quantity' => 117347966]],
                'subsidiaries' => [['name' => 'Công ty TNHH Phần mềm FPT', 'charter_capital' => 7_750_000_000_000, 'percent' => 100.0]],
                'affiliates' => [['name' => 'CTCP Synnex FPT', 'charter_capital' => 1_188_400_000_000, 'percent' => 48.0]],
                'events' => [['category' => 'DIVIDEND', 'title' => 'Trả cổ tức đợt 1 năm 2024 bằng tiền 400 VND', 'public_date' => '2024-05-01', 'exright_date' => '2024-05-20']],
                'errors' => [],
            ], $over),
        ]);
    }

    // ── show ────────────────────────────────────────────────────────────────

    #[Group('companyProfile')]
    public function test_show_renders_the_cached_profile_sections(): void
    {
        Queue::fake();
        $this->seedProfile();

        $response = $this->get('/company/fpt');   // lower-case is normalised

        $response->assertOk();
        $response->assertSee('Công ty Cổ phần FPT');
        $response->assertSee('122,9 nghìn tỷ ₫');
        $response->assertSee('Trương Gia Bình');
        $response->assertSee('CTCP Synnex FPT');
        $response->assertSee('Trả cổ tức đợt 1 năm 2024 bằng tiền 400 VND');
        $response->assertSee('id="cpOwnershipChart"', false);
        Queue::assertNothingPushed();   // fresh profile => no background refresh
    }

    #[Group('companyProfile')]
    public function test_a_stale_profile_is_still_shown_instantly_and_a_refresh_job_is_queued(): void
    {
        Queue::fake();
        $this->seedProfile('FPT', now()->subDays(CompanyProfile::STALE_DAYS + 2));

        $this->get('/company/FPT')->assertOk()->assertSee('Công ty Cổ phần FPT');

        Queue::assertPushed(SyncCompanyProfileJob::class, 1);
    }

    #[Group('companyProfile')]
    public function test_show_without_a_cached_profile_renders_the_first_visit_loader(): void
    {
        $response = $this->get('/company/HPG');

        $response->assertOk();
        $response->assertSee('Đang tải hồ sơ HPG lần đầu');
        $response->assertSee(route('company.load', 'HPG'), false);
    }

    #[Group('companyProfile')]
    public function test_show_for_a_remembered_unknown_symbol_says_so_instead_of_loading(): void
    {
        Cache::put('company-profile-missing:ZZZ', true, 900);

        $this->get('/company/ZZZ')->assertOk()->assertSee('Không tìm thấy thông tin cho mã ZZZ');
    }

    #[Group('companyProfile')]
    public function test_show_rejects_symbols_that_are_not_tickers(): void
    {
        $this->get('/company/A')->assertNotFound();
        $this->get('/company/WAYTOOLONGSYMBOL')->assertNotFound();
        $this->get('/company/FPT%3Bls')->assertNotFound();
    }

    #[Group('companyProfile')]
    public function test_partial_source_failures_show_a_notice_naming_the_missing_sections(): void
    {
        Queue::fake();
        $this->seedProfile('FPT', null, ['errors' => ['vci_events' => 'timeout']]);

        $this->get('/company/FPT')->assertOk()->assertSee('sự kiện doanh nghiệp');
    }

    // ── load (AJAX) ─────────────────────────────────────────────────────────

    private function stubService(?array $scriptResult): void
    {
        $stub = Mockery::mock(CompanyProfileService::class, [app(\App\Frontend\Interfaces\CompanyProfileRepositoryInterface::class)])
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $stub->shouldReceive('runScript')->andReturn($scriptResult);
        $this->app->instance(CompanyProfileService::class, $stub);
    }

    #[Group('companyProfile')]
    public function test_load_fetches_and_persists_a_profile_on_first_visit(): void
    {
        $this->stubService(['symbol' => 'HPG', 'overview' => ['name' => 'Hòa Phát'], 'errors' => []]);

        $this->postJson('/company/HPG/load')->assertOk()->assertJsonPath('success', true);

        $this->assertSame('Hòa Phát', CompanyProfile::where('symbol', 'HPG')->first()->data['overview']['name']);
    }

    #[Group('companyProfile')]
    public function test_load_for_an_unknown_company_is_404_and_remembered(): void
    {
        // NB: one stubbed request per test — the router caches the resolved controller on the Route,
        // so re-binding the service between two requests to the same route would be ignored.
        $this->stubService(['error' => 'Không tìm thấy', 'transient' => false]);

        $this->postJson('/company/ZZZ/load')->assertNotFound()->assertJsonPath('success', false);
        $this->assertTrue(Cache::has('company-profile-missing:ZZZ'));
    }

    #[Group('companyProfile')]
    public function test_load_is_502_when_the_source_is_down_and_that_is_not_remembered(): void
    {
        $this->stubService(null);

        $this->postJson('/company/HPG/load')->assertStatus(502);
        $this->assertFalse(Cache::has('company-profile-missing:HPG'));
    }

    #[Group('companyProfile')]
    public function test_forced_refresh_is_rate_limited_with_429(): void
    {
        $this->stubService(['symbol' => 'HPG', 'overview' => ['name' => 'Hòa Phát'], 'errors' => []]);

        $this->postJson('/company/HPG/load?force=1')->assertOk();
        $this->postJson('/company/HPG/load?force=1')->assertStatus(429);
    }

    #[Group('companyProfile')]
    public function test_load_is_post_only(): void
    {
        $this->get('/company/HPG/load')->assertStatus(405);
    }
}
