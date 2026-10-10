<?php

namespace Tests\Feature\Frontend\Services;

use App\Frontend\Services\MarketOverviewService;
use App\Frontend\Services\WorldMarketService;
use App\Jobs\SyncWorldMarketsJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

/** The world indices strip: cache-only reads, deduplicated background refresh, and the (stubbed) script boundary. No Python, no network. */
class WorldMarketServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function payload(array $over = []): array
    {
        return array_replace([
            'fetched_at' => '2026-10-08T03:15:00Z',
            'markets' => [
                ['code' => 'INX', 'name' => 'S&P 500', 'region' => 'Mỹ', 'close' => 7801.77, 'previous' => 7818.93, 'change' => -17.16, 'percent' => -0.22, 'date' => '2026-10-07', 'series' => [7700.0, 7750.0, 7818.93, 7801.77]],
                ['code' => 'N225', 'name' => 'Nikkei 225', 'region' => 'Nhật', 'close' => 69042.11, 'previous' => 70040.0, 'change' => -997.89, 'percent' => -1.42, 'date' => '2026-10-07', 'series' => [70000.0, 70040.0, 69042.11]],
            ],
            'errors' => [],
        ], $over);
    }

    /** A service whose Python boundary answers `$answer` (and is expected to be called `$times` times). */
    private function service(?array $answer, int $times = 1): WorldMarketService
    {
        $mock = Mockery::mock(WorldMarketService::class, [$this->app->make(MarketOverviewService::class)])->makePartial()->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('runScript')->times($times)->andReturn($answer);

        return $mock;
    }

    #[Group('worldMarkets')]
    public function test_with_nothing_cached_the_strip_is_absent_and_exactly_one_refresh_is_queued(): void
    {
        $service = $this->app->make(WorldMarketService::class);

        $this->assertNull($service->snapshot());
        $this->assertNull($service->snapshot());

        Queue::assertPushed(SyncWorldMarketsJob::class, 1);
    }

    #[Group('worldMarkets')]
    public function test_a_synced_answer_is_served_with_sparklines_and_no_further_refresh(): void
    {
        $this->assertSame(['markets' => 2, 'errors' => []], $this->service($this->payload())->sync());
        Queue::assertNothingPushed();

        $snapshot = $this->app->make(WorldMarketService::class)->snapshot();

        $this->assertSame(['INX', 'N225'], array_column($snapshot['markets'], 'code'));
        $this->assertNotSame('', $snapshot['markets'][0]['spark'], 'a sparkline is drawn from the series');
        $this->assertSame(now()->toDateString(), $snapshot['synced_at']->toDateString());
        Queue::assertNotPushed(SyncWorldMarketsJob::class);
    }

    #[Group('worldMarkets')]
    public function test_an_old_answer_is_still_shown_while_a_refresh_is_queued(): void
    {
        $this->service($this->payload())->sync();
        $this->travel(WorldMarketService::STALE_MINUTES + 5)->minutes();

        $snapshot = $this->app->make(WorldMarketService::class)->snapshot();

        $this->assertCount(2, $snapshot['markets'], 'the strip keeps showing the old numbers meanwhile');
        Queue::assertPushed(SyncWorldMarketsJob::class, 1);
    }

    #[Group('worldMarkets')]
    public function test_a_failed_or_empty_run_never_overwrites_a_good_answer(): void
    {
        $this->service($this->payload())->sync();
        $before = Cache::get(WorldMarketService::CACHE_KEY);

        $this->assertArrayHasKey('error', $this->service(null)->sync());
        $this->assertSame('boom', $this->service(['error' => 'boom'])->sync()['error']);
        $this->assertArrayHasKey('error', $this->service(['markets' => [], 'errors' => ['INX' => 'x']])->sync());

        $this->assertSame($before, Cache::get(WorldMarketService::CACHE_KEY));
    }

    #[Group('worldMarkets')]
    public function test_markets_that_failed_are_reported_but_the_others_are_kept(): void
    {
        $result = $this->service($this->payload(['errors' => ['DAX' => 'fewer than two daily bars']]))->sync();

        $this->assertSame(2, $result['markets']);
        $this->assertSame(['DAX' => 'fewer than two daily bars'], $result['errors']);
    }

    #[Group('worldMarkets')]
    public function test_refreshes_are_deduplicated_for_five_minutes(): void
    {
        $service = $this->app->make(WorldMarketService::class);

        $this->assertTrue($service->queueRefresh());
        $this->assertFalse($service->queueRefresh());
    }

    // ── command and job ─────────────────────────────────────────────────────

    #[Group('worldMarkets')]
    public function test_the_command_reports_success_and_failure_through_its_exit_code(): void
    {
        $ok = Mockery::mock(WorldMarketService::class);
        $ok->shouldReceive('sync')->once()->andReturn(['markets' => 8, 'errors' => ['DAX' => 'no data']]);
        $this->app->instance(WorldMarketService::class, $ok);

        $this->artisan('sync:world-markets')->expectsOutputToContain('World markets stored (8 markets).')->assertExitCode(0);
    }

    #[Group('worldMarkets')]
    public function test_the_command_fails_loudly_when_nothing_answers(): void
    {
        $bad = Mockery::mock(WorldMarketService::class);
        $bad->shouldReceive('sync')->once()->andReturn(['error' => 'Không có chỉ số thế giới nào trả lời.']);
        $this->app->instance(WorldMarketService::class, $bad);

        $this->artisan('sync:world-markets')->assertExitCode(1);
    }

    #[Group('worldMarkets')]
    public function test_the_job_throws_on_an_error_so_the_queue_retries_and_describes_itself_for_the_monitor(): void
    {
        $service = Mockery::mock(WorldMarketService::class);
        $service->shouldReceive('sync')->once()->andReturn(['error' => 'boom']);

        $job = new SyncWorldMarketsJob;
        $this->assertStringContainsString('thế giới', $job->queueSummary());
        $this->expectException(RuntimeException::class);
        $job->handle($service);
    }

    #[Group('worldMarkets')]
    public function test_the_refresh_runs_every_thirty_minutes_in_the_schedule(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('sync:world-markets')->assertExitCode(0);
    }
}
