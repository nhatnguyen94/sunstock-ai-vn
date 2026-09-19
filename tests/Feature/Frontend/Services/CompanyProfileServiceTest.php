<?php

namespace Tests\Feature\Frontend\Services;

use App\Frontend\Interfaces\CompanyProfileRepositoryInterface;
use App\Frontend\Services\CompanyProfileService;
use App\Jobs\SyncCompanyProfileJob;
use App\Models\CompanyProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Feature because the service uses Cache / Queue facades (CACHE_STORE=array). The repository is
 * mocked and the Python boundary (runScript) is stubbed — no DB, network or subprocess.
 */
class CompanyProfileServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function service(CompanyProfileRepositoryInterface $repo, ?callable $stub = null): CompanyProfileService
    {
        $mock = Mockery::mock(CompanyProfileService::class, [$repo])->makePartial()->shouldAllowMockingProtectedMethods();
        if ($stub) {
            $stub($mock);
        }

        return $mock;
    }

    private function profile(array $data = [], ?Carbon $syncedAt = null): CompanyProfile
    {
        return new CompanyProfile([
            'symbol' => 'FPT',
            'data' => $data + ['overview' => ['name' => 'FPT Corp']],
            'synced_at' => $syncedAt ?? now(),
        ]);
    }

    // ── normalizeSymbol ─────────────────────────────────────────────────────

    #[Group('companyProfile')]
    public function test_normalize_symbol_uppercases_and_rejects_anything_that_is_not_a_ticker(): void
    {
        $this->assertSame('FPT', CompanyProfileService::normalizeSymbol(' fpt '));
        $this->assertSame('E1VFVN30', CompanyProfileService::normalizeSymbol('e1vfvn30'));
        $this->assertNull(CompanyProfileService::normalizeSymbol('A'));                 // too short
        $this->assertNull(CompanyProfileService::normalizeSymbol('WAYTOOLONGSYMBOL'));  // too long
        $this->assertNull(CompanyProfileService::normalizeSymbol('FPT; rm -rf /'));     // never reaches a shell
        $this->assertNull(CompanyProfileService::normalizeSymbol(''));
    }

    // ── load / sync ─────────────────────────────────────────────────────────

    #[Group('companyProfile')]
    public function test_load_returns_the_cached_profile_without_running_python(): void
    {
        $repo = Mockery::mock(CompanyProfileRepositoryInterface::class);
        $repo->shouldReceive('find')->with('FPT')->andReturn($profile = $this->profile());

        $svc = $this->service($repo, fn ($m) => $m->shouldNotReceive('runScript'));

        $this->assertSame($profile, $svc->load('FPT')['profile']);
    }

    #[Group('companyProfile')]
    public function test_load_on_a_miss_fetches_stores_and_returns_the_profile(): void
    {
        $payload = ['symbol' => 'HPG', 'overview' => ['name' => 'Hòa Phát'], 'errors' => []];
        $stored = $this->profile($payload);

        $repo = Mockery::mock(CompanyProfileRepositoryInterface::class);
        $repo->shouldReceive('find')->with('HPG')->andReturn(null);
        $repo->shouldReceive('upsert')->once()->with('HPG', $payload)->andReturn($stored);

        $svc = $this->service($repo, fn ($m) => $m->shouldReceive('runScript')->once()->with('HPG')->andReturn($payload));

        $this->assertSame($stored, $svc->load('HPG')['profile']);
    }

    #[Group('companyProfile')]
    public function test_an_unknown_symbol_is_remembered_so_repeated_requests_do_not_respawn_python(): void
    {
        $repo = Mockery::mock(CompanyProfileRepositoryInterface::class);
        $repo->shouldReceive('find')->andReturn(null);
        $repo->shouldNotReceive('upsert');

        $svc = $this->service($repo, fn ($m) => $m->shouldReceive('runScript')->once()
            ->andReturn(['error' => 'Không tìm thấy thông tin công ty cho mã ZZZ', 'transient' => false]));

        $first = $svc->load('ZZZ');
        $second = $svc->load('ZZZ');   // short-circuited by the negative cache — once() above enforces it

        $this->assertTrue($first['not_found']);
        $this->assertTrue($second['not_found']);
        $this->assertTrue($svc->isKnownMissing('ZZZ'));
    }

    #[Group('companyProfile')]
    public function test_a_transient_failure_is_not_remembered_and_a_timeout_is_treated_as_transient(): void
    {
        $repo = Mockery::mock(CompanyProfileRepositoryInterface::class);
        $repo->shouldReceive('find')->andReturn(null);

        // 1st: source reports "every section failed, symbol not rejected" (outage); 2nd: no output at all (timeout)
        $svc = $this->service($repo, fn ($m) => $m->shouldReceive('runScript')->twice()->andReturn(
            ['error' => 'Không tìm thấy', 'transient' => true],
            null
        ));

        $first = $svc->load('FPT');
        $second = $svc->load('FPT');

        $this->assertFalse($first['not_found']);
        $this->assertFalse($second['not_found']);
        $this->assertFalse($svc->isKnownMissing('FPT'));   // a bad minute must not hide a valid symbol
    }

    #[Group('companyProfile')]
    public function test_a_successful_sync_clears_an_earlier_not_found_mark(): void
    {
        $repo = Mockery::mock(CompanyProfileRepositoryInterface::class);
        $repo->shouldReceive('upsert')->andReturn($this->profile());
        Cache::put('company-profile-missing:FPT', true, 900);

        $svc = $this->service($repo, fn ($m) => $m->shouldReceive('runScript')->andReturn(['symbol' => 'FPT', 'overview' => ['name' => 'x']]));
        $svc->sync('FPT');

        $this->assertFalse($svc->isKnownMissing('FPT'));
    }

    #[Group('companyProfile')]
    public function test_forced_refresh_ignores_the_cache_but_is_rate_limited_per_symbol(): void
    {
        $repo = Mockery::mock(CompanyProfileRepositoryInterface::class);
        $repo->shouldNotReceive('find');   // force = never trust the cached copy
        $repo->shouldReceive('upsert')->once()->andReturn($this->profile());

        $svc = $this->service($repo, fn ($m) => $m->shouldReceive('runScript')->once()
            ->andReturn(['symbol' => 'FPT', 'overview' => ['name' => 'x']]));

        $this->assertArrayHasKey('profile', $svc->load('FPT', true));

        $again = $svc->load('FPT', true);
        $this->assertTrue($again['cooldown']);
    }

    // ── stale-while-revalidate ──────────────────────────────────────────────

    #[Group('companyProfile')]
    public function test_a_stale_profile_is_served_immediately_and_queues_exactly_one_refresh(): void
    {
        Queue::fake();
        $repo = Mockery::mock(CompanyProfileRepositoryInterface::class);
        $repo->shouldReceive('find')->andReturn($this->profile([], now()->subDays(CompanyProfile::STALE_DAYS + 1)));

        $svc = $this->service($repo);

        $this->assertNotNull($svc->find('FPT'));
        $this->assertNotNull($svc->find('FPT'));   // second page view while the job is pending

        Queue::assertPushed(SyncCompanyProfileJob::class, 1);
    }

    #[Group('companyProfile')]
    public function test_a_fresh_profile_queues_nothing(): void
    {
        Queue::fake();
        $repo = Mockery::mock(CompanyProfileRepositoryInterface::class);
        $repo->shouldReceive('find')->andReturn($this->profile());

        $this->service($repo)->find('FPT');

        Queue::assertNothingPushed();
    }

    // ── present() ───────────────────────────────────────────────────────────

    #[Group('companyProfile')]
    public function test_present_buckets_events_and_flags_upcoming_ones_by_their_key_date(): void
    {
        Carbon::setTestNow('2026-09-19');

        $events = [
            ['category' => 'DIVIDEND', 'title' => 'Cổ tức tiền mặt sắp GDKHQ', 'public_date' => '2026-09-10', 'exright_date' => '2026-09-25', 'record_date' => '2026-09-26'],
            ['category' => 'DIVIDEND', 'title' => 'Cổ tức đã qua', 'public_date' => '2025-01-10', 'exright_date' => '2025-01-20'],
            ['category' => 'SHAREHOLDER_MEETING', 'title' => 'ĐHCĐ sắp tới', 'public_date' => '2026-10-01'],
            ['category' => 'MAJOR_SHAREHOLDER_TRADING', 'title' => 'Nguyễn A - Đăng kí Mua 100 FPT', 'public_date' => '2026-12-01'],
            ['category' => 'OTHER', 'title' => 'Niêm yết thêm', 'public_date' => '2026-01-01'],
        ];

        $view = $this->service(Mockery::mock(CompanyProfileRepositoryInterface::class))
            ->present($this->profile(['events' => $events]));

        $this->assertSame(
            ['Cổ tức tiền mặt sắp GDKHQ', 'ĐHCĐ sắp tới'],   // soonest first; insider trades never count as "upcoming"
            array_column($view['upcoming'], 'title')
        );
        $this->assertCount(2, $view['dividends']);
        $this->assertCount(1, $view['meetings']);
        $this->assertCount(1, $view['other_events']);
        $this->assertSame('Mua', $view['insider_trades'][0]['side']);

        Carbon::setTestNow();
    }

    #[Group('companyProfile')]
    public function test_present_groups_officers_and_tolerates_a_missing_group(): void
    {
        $officers = [
            ['name' => 'A', 'group' => 'board'], ['name' => 'B', 'group' => 'executive'],
            ['name' => 'C', 'group' => 'supervisory'], ['name' => 'D'],   // no group => executive
        ];

        $view = $this->service(Mockery::mock(CompanyProfileRepositoryInterface::class))
            ->present($this->profile(['officers' => $officers]));

        $this->assertSame(['A'], array_column($view['officers']['board'], 'name'));
        $this->assertSame(['B', 'D'], array_column($view['officers']['executive'], 'name'));
        $this->assertSame(['C'], array_column($view['officers']['supervisory'], 'name'));
    }

    #[Group('companyProfile')]
    public function test_holders_chart_adds_an_others_slice_only_when_the_top_holders_sum_below_100(): void
    {
        $svc = $this->service(Mockery::mock(CompanyProfileRepositoryInterface::class));

        $below = $svc->present($this->profile(['shareholders' => [
            ['name' => 'A', 'percent' => 30.0], ['name' => 'B', 'percent' => 20.0],
        ]]));
        $this->assertSame(['A', 'B', 'Cổ đông khác'], $below['holders_chart']['labels']);
        $this->assertEqualsWithDelta(100.0, array_sum($below['holders_chart']['series']), 0.001);

        // Overlapping source data that already exceeds 100% must not get a negative filler slice
        $over = $svc->present($this->profile(['shareholders' => [
            ['name' => 'A', 'percent' => 70.0], ['name' => 'B', 'percent' => 45.0],
        ]]));
        $this->assertSame(['A', 'B'], $over['holders_chart']['labels']);

        $empty = $svc->present($this->profile(['shareholders' => []]));
        $this->assertSame([], $empty['holders_chart']['series']);
    }
}
