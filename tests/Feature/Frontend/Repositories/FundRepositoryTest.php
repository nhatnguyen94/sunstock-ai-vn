<?php

namespace Tests\Feature\Frontend\Repositories;

use App\Frontend\Repositories\FundRepository;
use App\Models\Fund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Real DB (RefreshDatabase) on purpose: the value here is the SQL itself —
 * NULLs-last ordering, LIKE escaping, grouping, upsert-by-short_name.
 */
class FundRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function fund(string $code, array $attrs = []): Fund
    {
        return Fund::create(array_merge([
            'short_name' => $code, 'name' => "QUỸ {$code}", 'fund_type' => 'Quỹ cổ phiếu', 'type_code' => 'STOCK',
            'fund_owner_name' => 'OWNER A', 'management_fee' => 1.5, 'nav' => 10000,
        ], $attrs));
    }

    #[Group('fundCatalog')]
    public function test_search_puts_null_returns_last_in_both_directions(): void
    {
        $this->fund('LOW', ['nav_change_12m' => -5]);
        $this->fund('HIGH', ['nav_change_12m' => 20]);
        $this->fund('YOUNG', ['nav_change_12m' => null]);   // too young for the 12m window

        $repo = new FundRepository();

        $this->assertSame(['HIGH', 'LOW', 'YOUNG'], $repo->search(['sort' => 'nav_change_12m', 'dir' => 'desc'])->pluck('short_name')->all());
        $this->assertSame(['LOW', 'HIGH', 'YOUNG'], $repo->search(['sort' => 'nav_change_12m', 'dir' => 'asc'])->pluck('short_name')->all());
    }

    #[Group('fundCatalog')]
    public function test_search_filters_by_type_owner_and_text_and_ignores_unknown_sort_columns(): void
    {
        $this->fund('AAA', ['type_code' => 'STOCK', 'fund_owner_name' => 'OWNER A']);
        $this->fund('BBB', ['type_code' => 'BOND', 'fund_owner_name' => 'OWNER B', 'name' => 'QUỸ TRÁI PHIẾU BẢO VIỆT']);

        $repo = new FundRepository();

        $this->assertSame(['BBB'], $repo->search(['type' => 'BOND'])->pluck('short_name')->all());
        $this->assertSame(['AAA'], $repo->search(['owner' => 'OWNER A'])->pluck('short_name')->all());
        // name match (same case as stored: SQLite only case-folds ASCII; MySQL's collation folds Vietnamese too)
        $this->assertSame(['BBB'], $repo->search(['q' => 'TRÁI PHIẾU'])->pluck('short_name')->all());
        $this->assertSame(['AAA'], $repo->search(['q' => 'aaa'])->pluck('short_name')->all());   // code match, case-insensitive
        // an injection-shaped sort key must not reach ORDER BY — falls back to the default column
        $this->assertCount(2, $repo->search(['sort' => 'id; DROP TABLE funds']));
        $this->assertSame(2, Fund::count());
    }

    #[Group('fundCatalog')]
    public function test_like_wildcards_in_the_search_term_are_treated_literally(): void
    {
        $this->fund('AAA');
        $this->fund('BBB');

        $this->assertCount(0, (new FundRepository())->search(['q' => '%']));   // would match everything if unescaped
    }

    #[Group('fundCatalog')]
    public function test_upsert_many_updates_existing_funds_instead_of_duplicating_them(): void
    {
        $repo = new FundRepository();

        $this->assertSame(2, $repo->upsertMany([
            ['short_name' => 'AAA', 'name' => 'A', 'nav' => 100, 'type_code' => 'STOCK'],
            ['short_name' => 'BBB', 'name' => 'B', 'nav' => 200, 'type_code' => 'BOND'],
            ['name' => 'no code => skipped'],
        ]));
        $repo->upsertMany([['short_name' => 'AAA', 'name' => 'A', 'nav' => 150, 'type_code' => 'STOCK']]);

        $this->assertSame(2, $repo->count());
        $this->assertEquals(150, Fund::where('short_name', 'AAA')->value('nav'));
        $this->assertNotNull($repo->lastSyncedAt());
    }

    #[Group('fundCatalog')]
    public function test_type_counts_type_stats_and_owners(): void
    {
        $this->fund('S1', ['type_code' => 'STOCK', 'nav_change_12m' => 10, 'management_fee' => 2, 'fund_owner_name' => 'OWNER B']);
        $this->fund('S2', ['type_code' => 'STOCK', 'nav_change_12m' => 20, 'management_fee' => 1, 'fund_owner_name' => 'OWNER A']);
        $this->fund('B1', ['type_code' => 'BOND', 'nav_change_12m' => null, 'fund_owner_name' => 'OWNER A']);

        $repo = new FundRepository();

        $this->assertEquals(['STOCK' => 2, 'BOND' => 1], $repo->typeCounts());   // GROUP BY order is unspecified
        $this->assertSame(15.0, $repo->typeStats()['STOCK']['avg_12m']);
        $this->assertSame(1.5, $repo->typeStats()['STOCK']['avg_fee']);
        $this->assertNull($repo->typeStats()['BOND']['avg_12m']);   // no data => null, not 0
        $this->assertSame(['OWNER A', 'OWNER B'], $repo->owners());
    }

    #[Group('fundCatalog')]
    public function test_find_many_keeps_the_requested_order_and_skips_unknown_codes(): void
    {
        $this->fund('AAA');
        $this->fund('BBB');

        $found = (new FundRepository())->findManyByShortNames(['BBB', 'NOPE', 'AAA']);

        $this->assertSame(['BBB', 'AAA'], $found->pluck('short_name')->all());
    }
}
