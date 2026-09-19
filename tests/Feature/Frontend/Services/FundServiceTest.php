<?php

namespace Tests\Feature\Frontend\Services;

use App\Frontend\Interfaces\FundRepositoryInterface;
use App\Frontend\Services\FundService;
use App\Models\Fund;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * "Feature" because the service uses Cache / Log facades (CACHE_STORE=array in phpunit.xml).
 * The repository is mocked and the Python subprocess boundary (runListScript / runDetailScript)
 * is stubbed via a partial mock, so nothing here touches the DB, network or Python.
 */
class FundServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function service(FundRepositoryInterface $repo, ?callable $stub = null): FundService
    {
        $mock = Mockery::mock(FundService::class, [$repo])->makePartial()->shouldAllowMockingProtectedMethods();
        if ($stub) {
            $stub($mock);
        }

        return $mock;
    }

    private function listingRow(array $over = []): array
    {
        return array_merge([
            'short_name' => 'DCDS', 'name' => 'QUỸ ĐẦU TƯ CỔ PHIẾU DRAGON CAPITAL', 'fund_type' => 'Quỹ cổ phiếu',
            'fund_owner_name' => 'DRAGON CAPITAL', 'management_fee' => 1.95, 'nav' => 95325.99,
            'nav_change_12m' => -11.34, 'not_a_column' => 'must be dropped',
        ], $over);
    }

    // ── filters / codes ─────────────────────────────────────────────────────

    #[Group('fundCatalog')]
    public function test_normalize_filters_whitelists_type_sort_and_direction(): void
    {
        $svc = $this->service(Mockery::mock(FundRepositoryInterface::class));

        $this->assertSame(
            ['type' => 'BOND', 'q' => 'dcds', 'owner' => null, 'sort' => 'nav_change_36m', 'dir' => 'asc'],
            $svc->normalizeFilters(['type' => 'bond', 'q' => '  dcds ', 'sort' => 'nav_change_36m', 'dir' => 'asc'])
        );

        // Garbage falls back to safe defaults (this is what stops user input reaching the SQL ORDER BY)
        $this->assertSame(
            ['type' => null, 'q' => null, 'owner' => null, 'sort' => 'nav_change_12m', 'dir' => 'desc'],
            $svc->normalizeFilters(['type' => 'DROP', 'sort' => 'id; DROP TABLE funds', 'dir' => 'sideways'])
        );
    }

    #[Group('fundCatalog')]
    public function test_parse_codes_validates_dedupes_case_insensitively_and_caps(): void
    {
        $svc = $this->service(Mockery::mock(FundRepositoryInterface::class));

        $this->assertSame(['DCDS', 'vcbf-bcf'], $svc->parseCodes('DCDS, vcbf-bcf ,dcds,,bad code!,x'));
        $this->assertSame(['A1', 'B2', 'C3', 'D4'], $svc->parseCodes('A1,B2,C3,D4,E5,F6'));
        $this->assertSame([], $svc->parseCodes(null));
    }

    #[Group('fundCatalog')]
    public function test_compare_funds_resolves_codes_case_insensitively_in_request_order(): void
    {
        $repo = Mockery::mock(FundRepositoryInterface::class);
        $repo->shouldReceive('search')->andReturn(new Collection([
            new Fund(['short_name' => 'DCDS']), new Fund(['short_name' => 'VCBF-BCF']), new Fund(['short_name' => 'SSISCA']),
        ]));

        $result = $this->service($repo)->compareFunds(['vcbf-bcf', 'NOPE', 'dcds']);

        $this->assertSame(['VCBF-BCF', 'DCDS'], $result->pluck('short_name')->all());
    }

    // ── sync ────────────────────────────────────────────────────────────────

    #[Group('fundCatalog')]
    public function test_sync_all_maps_listing_rows_and_derives_type_code(): void
    {
        $repo = Mockery::mock(FundRepositoryInterface::class);
        $repo->shouldReceive('upsertMany')->once()
            ->with(Mockery::on(function (array $rows) {
                return count($rows) === 2
                    && $rows[0]['short_name'] === 'DCDS' && $rows[0]['type_code'] === 'STOCK'
                    && $rows[1]['type_code'] === 'BOND'
                    && ! array_key_exists('not_a_column', $rows[0]);   // only real columns reach the DB layer
            }))->andReturn(2);

        $svc = $this->service($repo, fn ($m) => $m->shouldReceive('runListScript')->andReturn(['funds' => [
            $this->listingRow(),
            $this->listingRow(['short_name' => 'VFF', 'fund_type' => 'Quỹ trái phiếu']),
            ['short_name' => 'BROKEN'],   // no name => skipped
        ]]));

        $this->assertSame(['count' => 2], $svc->syncAll());
    }

    #[Group('fundCatalog')]
    public function test_sync_all_reports_python_failures_without_touching_the_db(): void
    {
        $repo = Mockery::mock(FundRepositoryInterface::class);
        $repo->shouldNotReceive('upsertMany');

        foreach ([null, ['error' => 'Fmarket down'], ['funds' => []]] as $scriptResult) {
            $svc = $this->service($repo, fn ($m) => $m->shouldReceive('runListScript')->andReturn($scriptResult));
            $this->assertArrayHasKey('error', $svc->syncAll());
        }
    }

    // ── catalog ─────────────────────────────────────────────────────────────

    #[Group('fundCatalog')]
    public function test_catalog_with_data_never_calls_python(): void
    {
        $repo = Mockery::mock(FundRepositoryInterface::class);
        $repo->shouldReceive('count')->andReturn(68);
        $repo->shouldReceive('search')->once()->andReturn(new Collection());
        $repo->shouldReceive('typeCounts')->andReturn(['STOCK' => 34]);
        $repo->shouldReceive('typeStats')->andReturn([]);
        $repo->shouldReceive('owners')->andReturn([]);
        $repo->shouldReceive('lastSyncedAt')->andReturn(null);

        $svc = $this->service($repo, fn ($m) => $m->shouldNotReceive('runListScript'));

        $data = $svc->catalog(['type' => 'STOCK']);

        $this->assertSame(68, $data['total']);
        $this->assertSame('STOCK', $data['filters']['type']);
        $this->assertNull($data['error']);
    }

    #[Group('fundCatalog')]
    public function test_catalog_on_an_empty_table_loads_the_listing_once_and_surfaces_a_failure(): void
    {
        $repo = Mockery::mock(FundRepositoryInterface::class);
        $repo->shouldReceive('count')->andReturn(0);
        $repo->shouldReceive('search')->andReturn(new Collection());
        $repo->shouldReceive('typeCounts')->andReturn([]);
        $repo->shouldReceive('typeStats')->andReturn([]);
        $repo->shouldReceive('owners')->andReturn([]);
        $repo->shouldReceive('lastSyncedAt')->andReturn(null);

        $svc = $this->service($repo, fn ($m) => $m->shouldReceive('runListScript')->once()->andReturn(null));

        $this->assertNotNull($svc->catalog([])['error']);
    }

    // ── detail ──────────────────────────────────────────────────────────────

    #[Group('fundCatalog')]
    public function test_detail_for_an_unknown_fund_is_not_found_and_spawns_nothing(): void
    {
        $repo = Mockery::mock(FundRepositoryInterface::class);
        $repo->shouldReceive('findByShortName')->with('NOPE')->andReturn(null);

        $svc = $this->service($repo, fn ($m) => $m->shouldNotReceive('runDetailScript'));

        $result = $svc->detail('NOPE');

        $this->assertTrue($result['not_found']);
    }

    #[Group('fundCatalog')]
    public function test_detail_adds_window_stats_and_is_cached_so_python_runs_once(): void
    {
        Cache::flush();
        $repo = Mockery::mock(FundRepositoryInterface::class);
        $repo->shouldReceive('findByShortName')->with('DCDS')->andReturn(new Fund(['short_name' => 'DCDS']));

        $nav = [['2026-08-01', 100.0], ['2026-08-15', 110.0], ['2026-09-01', 121.0]];
        $svc = $this->service($repo, fn ($m) => $m->shouldReceive('runDetailScript')->once()->with('DCDS')->andReturn([
            'nav' => $nav, 'top_holdings' => [], 'industries' => [], 'assets' => [], 'as_of' => null, 'errors' => [],
        ]));

        $first = $svc->detail('DCDS');
        $second = $svc->detail('DCDS');   // served from cache — runDetailScript()->once() would fail otherwise

        $this->assertSame(21.0, $first['stats']['ALL']['return_pct']);
        $this->assertSame($first, $second);
    }

    #[Group('fundCatalog')]
    public function test_a_failed_detail_fetch_is_not_cached(): void
    {
        Cache::flush();
        $repo = Mockery::mock(FundRepositoryInterface::class);
        $repo->shouldReceive('findByShortName')->andReturn(new Fund(['short_name' => 'DCDS']));

        $svc = $this->service($repo, fn ($m) => $m->shouldReceive('runDetailScript')->twice()->andReturn(null));

        $this->assertArrayHasKey('error', $svc->detail('DCDS'));
        $this->assertArrayHasKey('error', $svc->detail('DCDS'));   // retried, not served from a cached failure
    }
}
