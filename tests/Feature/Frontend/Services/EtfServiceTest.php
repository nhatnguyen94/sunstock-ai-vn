<?php

namespace Tests\Feature\Frontend\Services;

use App\Frontend\Repositories\EtfRepository;
use App\Frontend\Repositories\MarketSnapshotRepository;
use App\Frontend\Services\EtfService;
use App\Frontend\Services\MarketOverviewService;
use App\Models\Etf;
use App\Models\Stock;
use App\Models\StockPrice;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\BuildsMarketPayload;
use Tests\TestCase;

/**
 * RefreshDatabase: the point is the SQL and the maths over real stock_prices rows. Only the Python boundary
 * (runListScript) is stubbed.
 */
class EtfServiceTest extends TestCase
{
    use RefreshDatabase, BuildsMarketPayload;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Carbon::setTestNow('2026-09-30 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param array<int, array<string, mixed>>|null $script what get_etf_list.py would print (null = no JSON) */
    private function service(?array $script = null, bool $callsScript = false): EtfService
    {
        $mock = Mockery::mock(EtfService::class, [new EtfRepository, new MarketOverviewService(new MarketSnapshotRepository)])
            ->makePartial()->shouldAllowMockingProtectedMethods();

        if ($callsScript) {
            $mock->shouldReceive('runListScript')->once()->andReturn($script);
        } else {
            $mock->shouldReceive('runListScript')->never();
        }

        return $mock;
    }

    private function etf(string $symbol, string $name, string $kind = 'etf'): Etf
    {
        return Etf::create(['symbol' => $symbol, 'name' => $name, 'exchange' => 'HOSE', 'kind' => $kind, 'synced_at' => now()]);
    }

    /** Weekday bars over `$days` calendar days ending today: close `$start` + `$step` per bar (feed unit). */
    private function prices(string $symbol, int $days = 1200, float $start = 10.0, float $step = 0.005, int $volume = 100_000): void
    {
        $stock = Stock::firstOrCreate(['symbol' => $symbol], ['name' => null]);
        $rows = [];
        $n = 0;
        for ($i = $days; $i >= 0; $i--) {
            $d = now()->subDays($i);
            if ($d->isWeekend()) {
                continue;
            }
            $rows[] = ['stock_id' => $stock->id, 'date' => $d->toDateString(), 'open' => $start + $n * $step, 'high' => $start + $n * $step, 'low' => $start + $n * $step,
                'close' => $start + $n * $step, 'volume' => $volume];
            $n++;
        }
        StockPrice::insert($rows);
    }

    private function roster(): array
    {
        return ['etfs' => [
            ['symbol' => 'FUESSV30', 'name' => 'Quỹ ETF SSIAM VN30', 'name_en' => 'SSIAM VN30 ETF', 'exchange' => 'HOSE'],
            ['symbol' => 'FUCTVGF5', 'name' => 'Quỹ đầu tư tăng trưởng Thiên Việt 5', 'name_en' => 'TVGF5', 'exchange' => 'HOSE'],
        ]];
    }

    // ── sync ────────────────────────────────────────────────────────────────

    #[Group('etf')]
    public function test_sync_stores_the_roster_and_tells_etfs_from_closed_end_funds(): void
    {
        $result = $this->service($this->roster(), true)->syncAll();

        $this->assertSame(2, $result['count']);
        $this->assertSame('etf', Etf::where('symbol', 'FUESSV30')->value('kind'));
        $this->assertSame('closed', Etf::where('symbol', 'FUCTVGF5')->value('kind'));
        $this->assertNotNull(Etf::first()->synced_at);
    }

    #[Group('etf')]
    public function test_a_second_sync_updates_in_place_and_removes_funds_that_are_no_longer_listed(): void
    {
        $this->etf('FUEGONE', 'Quỹ ETF OLD VN30');
        $this->service($this->roster(), true)->syncAll();
        $again = ['etfs' => [['symbol' => 'FUESSV30', 'name' => 'Quỹ ETF SSIAM VN30 (đổi tên)', 'name_en' => null, 'exchange' => 'HOSE']]];

        $result = $this->service($again, true)->syncAll();

        $this->assertSame(1, Etf::count());
        $this->assertSame('Quỹ ETF SSIAM VN30 (đổi tên)', Etf::first()->name);
        $this->assertSame(1, $result['pruned']);
    }

    #[Group('etf')]
    public function test_sync_failures_return_an_error_and_never_wipe_the_existing_roster(): void
    {
        $this->etf('FUESSV30', 'Quỹ ETF SSIAM VN30');

        foreach ([null, ['error' => 'rate limit'], ['etfs' => []], ['etfs' => [['symbol' => 'bad symbol!', 'name' => 'x']]]] as $script) {
            $this->assertArrayHasKey('error', $this->service($script, true)->syncAll());
        }

        $this->assertSame(1, Etf::count());
    }

    // ── first visit ─────────────────────────────────────────────────────────

    #[Group('etf')]
    public function test_the_first_ever_visit_loads_the_roster_once_then_it_is_database_only(): void
    {
        $service = $this->service($this->roster(), true);

        $first = $service->catalog([]);
        $second = $service->catalog([]);   // runListScript is expected once only

        $this->assertNull($first['error']);
        $this->assertSame(2, $first['total']);
        $this->assertSame(2, $second['total']);
    }

    #[Group('etf')]
    public function test_a_failed_first_load_surfaces_the_message_and_an_empty_table(): void
    {
        $r = $this->service(['error' => 'KBS down'], true)->catalog([]);

        $this->assertSame('KBS down', $r['error']);
        $this->assertSame(0, $r['total']);
        $this->assertSame([], $r['rows']);
    }

    // ── rows ────────────────────────────────────────────────────────────────

    #[Group('etf')]
    public function test_a_live_quote_wins_and_is_shown_in_whole_vnd(): void
    {
        $this->etf('FUESSV30', 'Quỹ ETF SSIAM VN30');
        $this->prices('FUESSV30');
        $this->seedMarketSnapshot(['quotes' => ['FUESSV30' => [24400, 24200, 0.83, 16_000, 390_000_000, 25900, 22500, 24200, 24500, 24100]]]);

        $row = $this->service()->catalog([])['rows'][0];

        $this->assertSame('live', $row['source']);
        $this->assertSame(24400, $row['price']);
        $this->assertSame(0.83, $row['percent']);
        $this->assertSame(390_000_000, $row['value']);
    }

    #[Group('etf')]
    public function test_without_a_live_quote_the_last_close_is_converted_from_thousands_and_compared_with_the_previous_one(): void
    {
        $this->etf('FUESSV30', 'Quỹ ETF SSIAM VN30');
        $stock = Stock::create(['symbol' => 'FUESSV30']);
        StockPrice::create(['stock_id' => $stock->id, 'date' => '2026-09-29', 'open' => 34.0, 'high' => 34.0, 'low' => 34.0, 'close' => 34.00, 'volume' => 10]);
        StockPrice::create(['stock_id' => $stock->id, 'date' => '2026-09-30', 'open' => 34.55, 'high' => 34.55, 'low' => 34.55, 'close' => 34.55, 'volume' => 20]);

        $row = $this->service()->catalog([])['rows'][0];

        $this->assertSame('eod', $row['source']);
        $this->assertSame(34550, $row['price']);          // 34.55 thousand VND, not 34.55 ₫
        $this->assertSame(550, $row['change']);
        $this->assertSame(1.62, $row['percent']);
        $this->assertSame('2026-09-30', $row['as_of']);
        $this->assertSame(20, $row['volume']);
    }

    #[Group('etf')]
    public function test_returns_and_liquidity_come_from_the_stored_history_and_young_funds_have_no_long_windows(): void
    {
        $this->etf('FUEOLD', 'Quỹ ETF OLD VN30');
        $this->etf('FUENEW', 'Quỹ ETF NEW VN30');
        $this->prices('FUEOLD', 1200);
        $this->prices('FUENEW', 150);

        $rows = collect($this->service()->catalog([])['rows'])->keyBy('symbol');

        $this->assertNotNull($rows['FUEOLD']['r3y']);
        $this->assertNotNull($rows['FUEOLD']['r1y']);
        $this->assertNotNull($rows['FUENEW']['r3m']);
        $this->assertNull($rows['FUENEW']['r1y']);
        $this->assertNull($rows['FUENEW']['r3y']);
        $this->assertGreaterThan(0, $rows['FUEOLD']['avg_value']);
    }

    // ── sorting and filtering ───────────────────────────────────────────────

    private function threeFunds(): void
    {
        foreach (['FUEAAA' => 'Quỹ ETF SSIAM VN30', 'FUEBBB' => 'Quỹ ETF MAFM VN30', 'FUECCC' => 'Quỹ ETF KIM GROWTH VNX50'] as $s => $n) {
            $this->etf($s, $n);
        }
        $this->etf('FUCCLO', 'Quỹ đầu tư tăng trưởng Thiên Việt 5', 'closed');
        $this->prices('FUEAAA', 1200, 10, 0.005, 300_000);
        $this->prices('FUEBBB', 1200, 10, 0.010, 100_000);
        $this->prices('FUECCC', 300, 10, 0.010, 200_000);       // too young for 1Y
    }

    #[Group('etf')]
    public function test_the_default_order_is_by_liquidity_and_a_bad_sort_key_falls_back_to_it(): void
    {
        $this->threeFunds();

        $default = array_column($this->service()->catalog([])['rows'], 'symbol');
        $bad = array_column($this->service()->catalog(['sort' => 'symbol; DROP TABLE etfs', 'dir' => 'sideways'])['rows'], 'symbol');

        $this->assertSame(['FUEAAA', 'FUECCC', 'FUEBBB'], array_slice($default, 0, 3));
        $this->assertSame($default, $bad);
    }

    #[Group('etf')]
    public function test_rows_without_a_value_for_the_sort_column_always_go_last(): void
    {
        $this->threeFunds();   // FUECCC has no 1Y return, FUCCLO has no prices at all

        $desc = array_column($this->service()->catalog(['sort' => 'r1y', 'dir' => 'desc'])['rows'], 'symbol');
        $asc = array_column($this->service()->catalog(['sort' => 'r1y', 'dir' => 'asc'])['rows'], 'symbol');

        $this->assertSame(['FUEBBB', 'FUEAAA'], array_slice($desc, 0, 2));
        $this->assertSame(['FUEAAA', 'FUEBBB'], array_slice($asc, 0, 2));
        $this->assertContains(end($desc), ['FUECCC', 'FUCCLO']);
        $this->assertContains(end($asc), ['FUECCC', 'FUCCLO']);
    }

    #[Group('etf')]
    public function test_filters_by_kind_index_and_text_and_reports_the_counts_and_indices(): void
    {
        $this->threeFunds();
        $s = $this->service();

        $this->assertCount(1, $s->catalog(['kind' => 'closed'])['rows']);
        $this->assertCount(3, $s->catalog(['kind' => 'etf'])['rows']);
        $this->assertSame(['FUEAAA', 'FUEBBB'], array_column($s->catalog(['index' => 'VN30', 'sort' => 'symbol', 'dir' => 'asc'])['rows'], 'symbol'));
        $this->assertSame(['FUEAAA'], array_column($s->catalog(['q' => 'ssiam'])['rows'], 'symbol'));
        $this->assertSame(['FUECCC'], array_column($s->catalog(['q' => 'kim'])['rows'], 'symbol'));

        $all = $s->catalog([]);
        $this->assertSame(['VN30', 'VNX50'], $all['indices']);
        $this->assertSame(['etf' => 3, 'closed' => 1], $all['counts']);
    }

    #[Group('etf')]
    public function test_filter_values_are_normalised_and_junk_is_dropped(): void
    {
        $f = $this->service()->normalizeFilters(['kind' => 'zzz', 'index' => "VN30'; --", 'q' => str_repeat('a', 80), 'sort' => ['x'], 'dir' => 'desc']);

        $this->assertNull($f['kind']);
        $this->assertNull($f['index']);
        $this->assertSame(40, mb_strlen($f['q']));
        $this->assertSame('avg_value', $f['sort']);
    }

    // ── detail ──────────────────────────────────────────────────────────────

    #[Group('etf')]
    public function test_detail_is_null_for_malformed_or_unknown_symbols(): void
    {
        $this->etf('FUESSV30', 'Quỹ ETF SSIAM VN30');
        $s = $this->service();

        $this->assertNull($s->detail('nope1'));
        $this->assertNull($s->detail("FUESSV30\n"));
        $this->assertNull($s->detail('../etc'));
        $this->assertNull($s->detail(''));
    }

    #[Group('etf')]
    public function test_detail_returns_windows_ytd_range_series_and_the_funds_tracking_the_same_index(): void
    {
        $this->threeFunds();

        $d = $this->service()->detail('fueaaa');   // lower case is accepted

        $this->assertSame('FUEAAA', $d['etf']->symbol);
        $this->assertSame(['1M', '3M', '6M', '1Y', '3Y'], array_keys($d['windows']));
        $this->assertNotNull($d['windows']['3Y']['return_pct']);
        $this->assertNotNull($d['ytd']);
        $this->assertNotNull($d['range']['high']);
        $this->assertSame(['FUEBBB'], array_column($d['peers'], 'symbol'));
        $this->assertStringContainsString('30 cổ phiếu', $d['index_note']);
        $this->assertNotEmpty($d['series']);
    }

    #[Group('etf')]
    public function test_a_closed_end_fund_has_no_index_and_no_peers(): void
    {
        $this->threeFunds();
        $this->prices('FUCCLO', 200);

        $d = $this->service()->detail('FUCCLO');

        $this->assertNull($d['row']['index']);
        $this->assertSame([], $d['peers']);
        $this->assertNull($d['index_note']);
    }
}
