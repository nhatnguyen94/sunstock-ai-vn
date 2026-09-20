<?php

namespace Tests\Feature\Frontend\Services;

use App\Frontend\Repositories\MarketSnapshotRepository;
use App\Frontend\Services\MarketOverviewService;
use App\Jobs\SyncMarketOverviewJob;
use App\Models\MarketSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\BuildsMarketPayload;
use Tests\TestCase;

/**
 * RefreshDatabase: the SQL/JSON storage of snapshots is the point. Only the Python boundary (runScript) is stubbed.
 */
class MarketOverviewServiceTest extends TestCase
{
    use RefreshDatabase, BuildsMarketPayload;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function service(?array $scriptOutput = null, bool $callsScript = false): MarketOverviewService
    {
        $mock = Mockery::mock(MarketOverviewService::class, [new MarketSnapshotRepository])->makePartial()->shouldAllowMockingProtectedMethods();
        if ($callsScript) {
            $mock->shouldReceive('runScript')->once()->andReturn($scriptOutput);
        } else {
            $mock->shouldReceive('runScript')->never();
        }

        return $mock;
    }

    // ── market clock ────────────────────────────────────────────────────────

    #[Group('marketOverview')]
    public function test_market_is_open_on_weekdays_between_9_and_15_vietnam_time_only(): void
    {
        $s = $this->service();
        // 2026-09-21 is a Monday; 03:00 UTC = 10:00 in Vietnam
        $this->assertTrue($s->isMarketOpen(Carbon::parse('2026-09-21 03:00:00', 'UTC')));
        $this->assertFalse($s->isMarketOpen(Carbon::parse('2026-09-21 01:59:00', 'UTC')));   // 08:59
        $this->assertFalse($s->isMarketOpen(Carbon::parse('2026-09-21 08:00:00', 'UTC')));   // 15:00 = closed
        $this->assertFalse($s->isMarketOpen(Carbon::parse('2026-09-19 03:00:00', 'UTC')));   // Saturday
        $this->assertFalse($s->isMarketOpen(Carbon::parse('2026-09-20 03:00:00', 'UTC')));   // Sunday
    }

    #[Group('marketOverview')]
    public function test_a_snapshot_goes_stale_after_5_minutes_in_session_and_6_hours_outside(): void
    {
        $s = $this->service();

        $this->assertFalse($s->isStale(now()->subMinutes(4), true));
        $this->assertTrue($s->isStale(now()->subMinutes(6), true));
        $this->assertFalse($s->isStale(now()->subHours(5), false));
        $this->assertTrue($s->isStale(now()->subHours(7), false));
        $this->assertTrue($s->isStale(null, false));
    }

    // ── sync ────────────────────────────────────────────────────────────────

    #[Group('marketOverview')]
    public function test_sync_stores_the_snapshot_with_quotes_on_the_newest_session_only(): void
    {
        $result = $this->service($this->marketPayload(), true)->sync();

        $this->assertSame('2026-09-18', $result['trade_date']);
        $this->assertSame(4, $result['symbols']);
        $snap = MarketSnapshot::first();
        $this->assertSame(1815.66, $snap->data['indices'][0]['close']);
        $this->assertArrayNotHasKey('quotes', $snap->data);          // quotes live in their own column
        $this->assertSame(71700, $snap->quotes['FPT'][0]);

        // a newer session arrives: the old row keeps its overview but drops the 1,500-symbol quote map
        $this->service($this->marketPayload(['trade_date' => '2026-09-21']), true)->sync();
        $this->assertSame(2, MarketSnapshot::count());
        $this->assertNull(MarketSnapshot::where('trade_date', '2026-09-18')->first()->quotes);
        $this->assertNotNull(MarketSnapshot::where('trade_date', '2026-09-21')->first()->quotes);
    }

    #[Group('marketOverview')]
    public function test_the_same_session_is_overwritten_not_duplicated(): void
    {
        $this->service($this->marketPayload(), true)->sync();
        $again = $this->marketPayload();
        $again['quotes']['FPT'][0] = 72000;
        $this->service($again, true)->sync();

        $this->assertSame(1, MarketSnapshot::count());
        $this->assertSame(72000, MarketSnapshot::first()->quotes['FPT'][0]);
    }

    #[Group('marketOverview')]
    public function test_a_half_empty_payload_never_overwrites_a_good_snapshot(): void
    {
        $this->service($this->marketPayload(), true)->sync();

        $broken = $this->marketPayload(['quotes' => [], 'errors' => ['board' => 'RetryError']]);
        $result = $this->service($broken, true)->sync();

        $this->assertStringContainsString('chưa đầy đủ', $result['error']);
        $this->assertSame(71700, MarketSnapshot::first()->quotes['FPT'][0]);
    }

    #[Group('marketOverview')]
    public function test_sync_reports_errors_from_python_and_bad_dates(): void
    {
        $this->assertArrayHasKey('error', $this->service(null, true)->sync());
        $this->assertSame('down', $this->service(['error' => 'down'], true)->sync()['error']);
        $this->assertArrayHasKey('error', $this->service($this->marketPayload(['trade_date' => 'yesterday']), true)->sync());
        $this->assertSame(0, MarketSnapshot::count());
    }

    // ── overview ────────────────────────────────────────────────────────────

    #[Group('marketOverview')]
    public function test_first_visit_with_no_snapshot_loads_live_once(): void
    {
        $o = $this->service($this->marketPayload(), true)->overview();

        $this->assertTrue($o['has_data']);
        $this->assertSame('2026-09-18', $o['trade_date']->toDateString());
    }

    #[Group('marketOverview')]
    public function test_first_visit_shows_an_error_state_when_the_source_is_down(): void
    {
        $o = $this->service(['error' => 'down'], true)->overview();

        $this->assertFalse($o['has_data']);
        $this->assertSame('down', $o['error']);
    }

    #[Group('marketOverview')]
    public function test_overview_sums_breadth_and_liquidity_across_exchanges(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-09-20 03:00:00');   // a Sunday: session complete
        $this->seedMarketSnapshot();

        $o = $this->service()->overview();

        $this->assertSame(185 + 82 + 100, $o['breadth']['advancers']);
        $this->assertSame(130 + 60 + 90, $o['breadth']['decliners']);
        $this->assertSame(90 + 157 + 20, $o['breadth']['unchanged']);
        $this->assertSame(3 + 9 + 28, $o['breadth']['ceiling']);
        $this->assertSame(22_904_000_000_000, $o['liquidity']['value']);
        $this->assertNull($o['liquidity']['change_percent']);   // no previous session yet
        $this->assertNotSame('', $o['indices'][0]['spark']);
        $this->assertCount(3, $o['vnindex_series']);
    }

    #[Group('marketOverview')]
    public function test_liquidity_is_compared_with_the_previous_session_once_this_one_is_complete(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-09-20 03:00:00');
        $prev = $this->marketPayload(['trade_date' => '2026-09-17']);
        foreach ($prev['exchanges'] as $k => $e) {
            $prev['exchanges'][$k]['value'] = (int) ($e['value'] / 1.1);    // yesterday was 10% lower
        }
        $this->seedMarketSnapshot($prev);
        $this->seedMarketSnapshot();

        $o = $this->service()->overview();

        $this->assertEqualsWithDelta(10.0, $o['liquidity']['change_percent'], 0.2);
    }

    #[Group('marketOverview')]
    public function test_no_liquidity_comparison_while_the_session_is_still_running(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-09-21 03:00:00');   // Monday 10:00 VN, market open
        $this->seedMarketSnapshot($this->marketPayload(['trade_date' => '2026-09-17']));
        $this->seedMarketSnapshot($this->marketPayload(['trade_date' => '2026-09-21']));

        $this->assertNull($this->service()->overview()['liquidity']['change_percent']);
    }

    #[Group('marketOverview')]
    public function test_a_stale_snapshot_queues_exactly_one_refresh(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-09-21 03:00:00');   // market open => 5 minute threshold
        $this->seedMarketSnapshot([], now()->subMinutes(20));

        $service = $this->service();   // runScript ->never(): the page is DB only
        $this->assertTrue($service->overview()['stale']);
        $service->overview();

        Queue::assertPushed(SyncMarketOverviewJob::class, 1);
    }

    #[Group('marketOverview')]
    public function test_a_fresh_snapshot_queues_nothing(): void
    {
        Queue::fake();
        $this->seedMarketSnapshot();

        $this->assertFalse($this->service()->overview()['stale']);
        Queue::assertNothingPushed();
    }

    // ── quotes / ticker ─────────────────────────────────────────────────────

    #[Group('marketOverview')]
    public function test_quotes_returns_only_known_symbols_with_the_change_worked_out(): void
    {
        $this->seedMarketSnapshot();

        $q = $this->service()->quotes(['FPT', 'NOPE', 'HPG']);

        $this->assertSame(['FPT', 'HPG'], array_keys($q));
        $this->assertSame(-2600, $q['FPT']['change']);
        $this->assertSame(-3.5, $q['FPT']['percent']);
        $this->assertSame(79500, $q['FPT']['ceiling']);
        $this->assertSame([], $this->service()->quotes([]));
    }

    #[Group('marketOverview')]
    public function test_ticker_lists_indices_then_the_most_traded_stocks_and_survives_an_empty_table(): void
    {
        $this->assertSame([], $this->service()->ticker());

        Cache::flush();
        $this->seedMarketSnapshot();
        $items = $this->service()->ticker();

        $labels = array_column($items, 'label');
        $this->assertSame(['VN-INDEX', 'VN30', 'HNX-INDEX', 'VIC', 'FPT', 'NVB'], $labels);
        $this->assertSame(-0.39, $items[0]['percent']);
        $this->assertNull($items[3]['value']);
    }

    #[Group('marketOverview')]
    public function test_spark_points_scale_into_the_box_and_need_two_values(): void
    {
        $s = $this->service();

        $this->assertSame('', $s->sparkPoints([5]));
        // viewBox 100x32 with 2 units of padding: the minimum sits at y=30, the maximum at y=2
        $this->assertSame('0,30 50,2 100,16', $s->sparkPoints([10, 20, 15]));
        $this->assertSame('0,30 100,30', $s->sparkPoints([7, 7]));   // flat line, no division by zero
    }
}
