<?php

namespace Tests\Feature\Console\Commands;

use App\Frontend\Interfaces\CompanyProfileRepositoryInterface;
use App\Frontend\Services\CompanyProfileService;
use App\Jobs\SyncCompanyProfileJob;
use App\Models\CompanyProfile;
use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * RefreshDatabase because --seed reads the real `stocks` table and the default targets come from
 * company_profiles. The service is mocked so no Python ever runs; --dispatch uses Queue::fake().
 */
class SyncCompanyProfilesTest extends TestCase
{
    use RefreshDatabase;

    private function mockService(): \Mockery\MockInterface
    {
        $mock = Mockery::mock(CompanyProfileService::class);
        $this->app->instance(CompanyProfileService::class, $mock);

        return $mock;
    }

    private function stale(string $symbol): void
    {
        CompanyProfile::create(['symbol' => $symbol, 'data' => [], 'synced_at' => now()->subDays(CompanyProfile::STALE_DAYS + 1)]);
    }

    #[Group('companyProfile')]
    public function test_symbol_option_syncs_just_that_symbol_and_reports_the_outcome(): void
    {
        $this->mockService()->shouldReceive('sync')->once()->with('FPT')->andReturn(['profile' => new CompanyProfile()]);

        $this->artisan('sync:company-profiles --symbol=fpt')
            ->expectsOutputToContain('OK   FPT')
            ->expectsOutputToContain('Synced: 1')
            ->assertExitCode(0);
    }

    #[Group('companyProfile')]
    public function test_invalid_symbol_option_is_rejected_before_any_work(): void
    {
        $this->mockService()->shouldNotReceive('sync');

        $this->artisan('sync:company-profiles --symbol="FPT;ls"')->assertExitCode(1);
    }

    #[Group('companyProfile')]
    public function test_default_run_refreshes_only_stale_profiles_and_distinguishes_missing_from_errors(): void
    {
        $this->stale('OLD1');
        $this->stale('OLD2');
        CompanyProfile::create(['symbol' => 'FRESH', 'data' => [], 'synced_at' => now()]);

        $svc = $this->mockService();
        $svc->shouldReceive('sync')->with('OLD1')->once()->andReturn(['error' => 'no such company', 'not_found' => true]);
        $svc->shouldReceive('sync')->with('OLD2')->once()->andReturn(['error' => 'source down', 'not_found' => false]);
        $svc->shouldNotReceive('sync')->with('FRESH');

        $this->artisan('sync:company-profiles')
            ->expectsOutputToContain('no profile available')
            ->expectsOutputToContain('ERR  OLD2')
            ->assertExitCode(1);   // a real error (not "not available") makes the run non-zero
    }

    #[Group('companyProfile')]
    public function test_dispatch_mode_queues_one_job_per_target_without_running_python(): void
    {
        Queue::fake();
        $this->stale('OLD1');
        $this->stale('OLD2');
        $this->mockService()->shouldNotReceive('sync');

        $this->artisan('sync:company-profiles --dispatch')
            ->expectsOutputToContain('Dispatched 2 profile jobs')
            ->assertExitCode(0);

        Queue::assertPushed(SyncCompanyProfileJob::class, 2);
    }

    #[Group('companyProfile')]
    public function test_seed_adds_stocks_that_have_no_profile_yet_up_to_the_limit(): void
    {
        Queue::fake();
        $this->stale('OLD1');
        foreach (['AAA', 'BBB', 'CCC', 'OLD1'] as $sym) {
            Stock::create(['symbol' => $sym, 'name' => $sym]);
        }
        $this->mockService();

        // limit 3 = 1 stale (OLD1) + 2 new; OLD1 is already profiled so it is not "seeded" twice
        $this->artisan('sync:company-profiles --seed --limit=3 --dispatch')
            ->expectsOutputToContain('Dispatched 3 profile jobs')
            ->assertExitCode(0);

        Queue::assertPushed(SyncCompanyProfileJob::class, 3);
    }

    #[Group('companyProfile')]
    public function test_nothing_to_do_exits_cleanly(): void
    {
        $this->mockService()->shouldNotReceive('sync');

        $this->artisan('sync:company-profiles')
            ->expectsOutputToContain('Nothing to sync')
            ->assertExitCode(0);
    }
}
