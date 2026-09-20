<?php

namespace Tests\Feature\Frontend\Services;

use App\Frontend\Interfaces\ExchangeRateRepositoryInterface;
use App\Frontend\Repositories\GoldPriceRepository;
use App\Frontend\Services\GoldPriceService;
use App\Jobs\SyncGoldPricesJob;
use App\Models\ExchangeRate;
use App\Models\GoldPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * RefreshDatabase because the point of these tests is the SQL (latest-per-product join, dedupe of unchanged
 * SJC quotes, history window). Only the Python boundary (runScript) is stubbed.
 */
class GoldPriceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function service(?array $scriptOutput = null, bool $callsScript = false, ?float $usdSell = null): GoldPriceService
    {
        $rates = Mockery::mock(ExchangeRateRepositoryInterface::class);
        $rates->shouldReceive('getLatestRate')->with('USD')->andReturn($usdSell ? new ExchangeRate(['currency_code' => 'USD', 'sell' => number_format($usdSell, 2)]) : null);

        $mock = Mockery::mock(GoldPriceService::class, [new GoldPriceRepository, $rates])->makePartial()->shouldAllowMockingProtectedMethods();
        if ($callsScript) {
            $mock->shouldReceive('runScript')->once()->andReturn($scriptOutput);
        } else {
            $mock->shouldReceive('runScript')->never();
        }

        return $mock;
    }

    private function quote(array $over = []): GoldPrice
    {
        return GoldPrice::create($over + [
            'source' => 'SJC', 'metal' => 'gold', 'product' => 'Vàng SJC 1L, 10L, 1KG', 'branch' => 'Hồ Chí Minh',
            'unit' => 'luong', 'buy_price' => 144_600_000, 'sell_price' => 147_600_000,
            'quoted_at' => now()->subMinutes(5), 'synced_at' => now()->subMinutes(5),
        ]);
    }

    private function script(array $over = []): array
    {
        return $over + [
            'fetched_at' => '2026-09-20T02:00:00Z',
            'sjc' => [['product' => 'Vàng SJC 1L, 10L, 1KG', 'branch' => 'Hồ Chí Minh', 'buy' => 144_600_000, 'sell' => 147_600_000]],
            'btmc' => [
                ['metal' => 'gold', 'product' => 'NHẪN TRÒN TRƠN (Vàng BTMC)', 'purity' => '999.9', 'buy' => 143_600_000, 'sell' => 147_600_000, 'unit' => 'luong', 'quoted_at' => '2026-09-20T01:30:00Z'],
                ['metal' => 'gold', 'product' => 'NHẪN TRÒN TRƠN (Vàng BTMC)', 'purity' => '999.9', 'buy' => 143_000_000, 'sell' => 147_000_000, 'unit' => 'luong', 'quoted_at' => '2026-09-20T00:30:00Z'],
                ['metal' => 'silver', 'product' => 'BẠC MIẾNG 1 LƯỢNG', 'purity' => null, 'buy' => 2_900_000, 'sell' => 3_100_000, 'unit' => 'pack', 'quoted_at' => '2026-09-20T01:30:00Z'],
            ],
            'world_usd_oz' => 4378.0,
            'errors' => [], 'warnings' => [],
        ];
    }

    // ── sync ────────────────────────────────────────────────────────────────

    #[Group('goldPrice')]
    public function test_sync_stores_sjc_btmc_and_silver_quotes_and_puts_the_world_price_on_the_newest_publication_only(): void
    {
        $result = $this->service($this->script(), true)->sync();

        $this->assertSame(4, $result['count']);
        $this->assertSame(1, GoldPrice::where('source', 'SJC')->count());
        $this->assertSame(1, GoldPrice::where('metal', 'silver')->count());

        $newest = GoldPrice::where('source', 'BTMC')->where('metal', 'gold')->orderByDesc('quoted_at')->first();
        $older = GoldPrice::where('source', 'BTMC')->where('metal', 'gold')->orderBy('quoted_at')->first();
        $this->assertSame(4378.0, $newest->world_price);
        $this->assertNull($older->world_price);
    }

    #[Group('goldPrice')]
    public function test_sync_twice_does_not_duplicate_quotes_and_sjc_only_stores_a_change(): void
    {
        $this->service($this->script(), true)->sync();
        $again = $this->service($this->script(['fetched_at' => '2026-09-20T02:15:00Z']), true)->sync();

        $this->assertSame(0, $again['count']);
        $this->assertSame(4, GoldPrice::count());

        // SJC moves 500k -> a new SJC row appears (and only that one)
        $moved = $this->script(['fetched_at' => '2026-09-20T02:30:00Z']);
        $moved['sjc'][0]['sell'] = 148_100_000;
        $this->assertSame(1, $this->service($moved, true)->sync()['count']);
        $this->assertSame(2, GoldPrice::where('source', 'SJC')->count());
    }

    #[Group('goldPrice')]
    public function test_sync_reports_an_error_when_python_returns_nothing_or_an_error_object(): void
    {
        $this->assertArrayHasKey('error', $this->service(null, true)->sync());
        $this->assertSame('boom', $this->service(['error' => 'boom'], true)->sync()['error']);
        $this->assertSame(0, GoldPrice::count());
    }

    // ── page ────────────────────────────────────────────────────────────────

    #[Group('goldPrice')]
    public function test_first_visit_with_an_empty_table_loads_live_once(): void
    {
        $page = $this->service($this->script(), true)->page();

        $this->assertTrue($page['has_data']);
        $this->assertSame(2, $page['btmc_gold']->count() + 1);  // one BTMC gold product (latest quote wins) + SJC
        $this->assertSame('Vàng SJC 1L, 10L, 1KG', $page['headline']['quote']->product);
    }

    #[Group('goldPrice')]
    public function test_first_visit_shows_the_error_state_when_the_source_is_down(): void
    {
        $page = $this->service(['error' => 'down'], true)->page();

        $this->assertFalse($page['has_data']);
        $this->assertSame('down', $page['error']);
    }

    #[Group('goldPrice')]
    public function test_page_is_db_only_and_queues_one_deduplicated_refresh_when_stale(): void
    {
        Queue::fake();
        $this->quote(['synced_at' => now()->subMinutes(45)]);

        $service = $this->service();   // runScript ->never()
        $service->page();
        $service->page();

        Queue::assertPushed(SyncGoldPricesJob::class, 1);
    }

    #[Group('goldPrice')]
    public function test_fresh_data_queues_nothing(): void
    {
        Queue::fake();
        $this->quote();

        $page = $this->service()->page();

        $this->assertFalse($page['stale']);
        Queue::assertNothingPushed();
    }

    #[Group('goldPrice')]
    public function test_sjc_branches_with_identical_prices_are_flagged_uniform(): void
    {
        Queue::fake();
        $this->quote();
        $this->quote(['branch' => 'Hà Nội']);
        $this->assertTrue($this->service()->page()['sjc_uniform']);

        $this->quote(['branch' => 'Đà Nẵng', 'sell_price' => 148_000_000]);
        $this->assertFalse($this->service()->page()['sjc_uniform']);
    }

    #[Group('goldPrice')]
    public function test_page_uses_only_the_newest_quote_per_product_and_computes_the_change_since_yesterday(): void
    {
        Queue::fake();
        $yesterdayClose = now('Asia/Ho_Chi_Minh')->startOfDay()->subHour()->utc();
        $this->quote(['quoted_at' => $yesterdayClose, 'buy_price' => 143_600_000, 'sell_price' => 146_600_000]);
        $this->quote(['quoted_at' => now()->subMinutes(30), 'buy_price' => 144_000_000, 'sell_price' => 147_000_000]);
        $this->quote(['quoted_at' => now()->subMinutes(5)]);   // 144.6 / 147.6

        $page = $this->service()->page();

        $this->assertSame(1, $page['sjc_branches']->count());
        $this->assertSame(147_600_000, $page['headline']['quote']->sell_price);
        $this->assertSame(1_000_000, $page['headline']['sell_change']);   // vs yesterday's 146.6
        $this->assertSame(1_000_000, $page['headline']['buy_change']);
    }

    #[Group('goldPrice')]
    public function test_world_comparison_converts_usd_per_oz_to_vnd_per_luong_and_the_domestic_premium(): void
    {
        Queue::fake();
        $this->quote(['world_price' => 4378.0]);

        $world = $this->service(null, false, 26210.0)->page()['world'];

        // 4378 * (37.5 / 31.1034768) * 26210
        $expected = (int) round(4378 * (37.5 / 31.1034768) * 26210);
        $this->assertSame($expected, (int) $world['vnd_luong']);
        $this->assertSame(147_600_000 - $expected, (int) $world['premium_vnd']);
        $this->assertEqualsWithDelta(($world['premium_vnd'] / $expected) * 100, $world['premium_percent'], 0.01);
    }

    #[Group('goldPrice')]
    public function test_world_comparison_degrades_without_a_usd_rate(): void
    {
        Queue::fake();
        $this->quote(['world_price' => 4378.0]);

        $world = $this->service()->page()['world'];

        $this->assertSame(4378.0, $world['usd_oz']);
        $this->assertNull($world['vnd_luong']);
        $this->assertNull($world['premium_percent']);
    }

    #[Group('goldPrice')]
    public function test_chart_options_offer_sjc_once_and_each_btmc_product(): void
    {
        Queue::fake();
        $this->quote();
        $this->quote(['branch' => 'Hà Nội']);
        $this->quote(['source' => 'BTMC', 'branch' => '', 'product' => 'NHẪN TRÒN TRƠN (Vàng BTMC)']);
        $this->quote(['source' => 'BTMC', 'branch' => '', 'product' => 'VÀNG MIẾNG SJC (Vàng SJC)']);

        $labels = array_column($this->service()->page()['chart_options'], 'label');

        $this->assertCount(3, $labels);
        $this->assertContains('SJC · Vàng SJC 1L, 10L, 1KG', $labels);
        $this->assertContains('BTMC · NHẪN TRÒN TRƠN', $labels);
    }

    // ── history ─────────────────────────────────────────────────────────────

    #[Group('goldPrice')]
    public function test_history_respects_the_range_window_and_carries_the_last_price_to_now(): void
    {
        $old = $this->quote(['quoted_at' => now()->subDays(20), 'buy_price' => 130_000_000]);
        $this->quote(['quoted_at' => now()->subHours(3), 'buy_price' => 144_000_000]);
        $service = $this->service();

        $week = $service->history($old->id, '7D');
        $this->assertCount(2, $week['points']);                       // the 3h-old quote + the carried "now" point
        $this->assertSame(144_000_000, $week['points'][0]['buy']);
        $this->assertSame($week['points'][0]['buy'], $week['points'][1]['buy']);

        $this->assertCount(3, $service->history($old->id, 'ALL')['points']);
        $this->assertSame('7D', $service->history($old->id, 'nonsense')['range']);
    }

    #[Group('goldPrice')]
    public function test_history_of_an_unknown_id_is_null(): void
    {
        $this->assertNull($this->service()->history(999, '7D'));
    }

    // ── manual refresh ──────────────────────────────────────────────────────

    #[Group('goldPrice')]
    public function test_refresh_runs_once_then_is_rate_limited(): void
    {
        $service = $this->service($this->script(), true);

        $this->assertSame(4, $service->refresh()['count']);

        $second = $service->refresh();
        $this->assertTrue($second['cooldown']);
        $this->assertArrayHasKey('error', $second);
    }

    #[Group('goldPrice')]
    public function test_queue_refresh_is_deduplicated(): void
    {
        Queue::fake();
        $service = $this->service();

        $this->assertTrue($service->queueRefresh());
        $this->assertFalse($service->queueRefresh());
        Queue::assertPushed(SyncGoldPricesJob::class, 1);
    }

    // ── model ───────────────────────────────────────────────────────────────

    #[Group('goldPrice')]
    public function test_model_spread_and_display_name(): void
    {
        $q = new GoldPrice(['product' => 'VÀNG MIẾNG SJC (Vàng SJC)', 'buy_price' => 143_600_000, 'sell_price' => 147_600_000]);

        $this->assertSame(4_000_000, $q->spread);
        $this->assertSame('VÀNG MIẾNG SJC', $q->display_name);
        $this->assertNull((new GoldPrice(['product' => 'X', 'buy_price' => 1]))->spread);
    }
}
