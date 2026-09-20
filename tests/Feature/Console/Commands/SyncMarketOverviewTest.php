<?php

namespace Tests\Feature\Console\Commands;

use App\Frontend\Services\MarketOverviewService;
use App\Jobs\SyncMarketOverviewJob;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** The command and the queued job are thin: they must report the service result and let failures surface. */
class SyncMarketOverviewTest extends TestCase
{
    #[Group('marketOverview')]
    public function test_the_command_reports_what_was_stored(): void
    {
        $mock = Mockery::mock(MarketOverviewService::class);
        $mock->shouldReceive('sync')->once()->andReturn(['trade_date' => '2026-09-18', 'symbols' => 1547, 'warnings' => ['một cảnh báo']]);
        $this->app->instance(MarketOverviewService::class, $mock);

        $this->artisan('sync:market-overview')
            ->expectsOutputToContain('một cảnh báo')
            ->expectsOutputToContain('2026-09-18 stored (1547 quotes)')
            ->assertExitCode(0);
    }

    #[Group('marketOverview')]
    public function test_the_command_fails_loudly_when_the_source_is_down(): void
    {
        $mock = Mockery::mock(MarketOverviewService::class);
        $mock->shouldReceive('sync')->once()->andReturn(['error' => 'Không lấy được dữ liệu']);
        $this->app->instance(MarketOverviewService::class, $mock);

        $this->artisan('sync:market-overview')->expectsOutputToContain('Không lấy được dữ liệu')->assertExitCode(1);
    }

    #[Group('marketOverview')]
    public function test_the_job_throws_on_an_error_so_the_queue_retries_and_names_itself_for_the_monitor(): void
    {
        $ok = Mockery::mock(MarketOverviewService::class);
        $ok->shouldReceive('sync')->once()->andReturn(['trade_date' => '2026-09-18', 'symbols' => 1, 'warnings' => []]);
        (new SyncMarketOverviewJob)->handle($ok);

        $this->assertStringContainsString('VN-Index', (new SyncMarketOverviewJob)->queueSummary());

        $bad = Mockery::mock(MarketOverviewService::class);
        $bad->shouldReceive('sync')->once()->andReturn(['error' => 'down']);
        $this->expectException(\RuntimeException::class);
        (new SyncMarketOverviewJob)->handle($bad);
    }
}
