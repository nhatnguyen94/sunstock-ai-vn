<?php

namespace Tests\Feature\Frontend\Repositories;

use App\Frontend\Repositories\StockRepository;
use App\Models\StockSymbol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Real DB: the value here is the SQL — relevance ordering and the trimmed result shape used by the
 * autocomplete dropdown (symbol, name, exchange only).
 */
class StockRepositorySearchTest extends TestCase
{
    use RefreshDatabase;

    private function symbols(string $q): array
    {
        return array_column(($this->app->make(StockRepository::class))->searchSymbols($q), 'symbol');
    }

    private function seed3(): void
    {
        StockSymbol::create(['symbol' => 'AFPT', 'name' => 'Company A', 'exchange' => 'HSX']);   // contains
        StockSymbol::create(['symbol' => 'FPTS', 'name' => 'Company B', 'exchange' => 'HSX']);   // prefix
        StockSymbol::create(['symbol' => 'FPT', 'name' => 'FPT Corp', 'exchange' => 'HSX']);     // exact
        StockSymbol::create(['symbol' => 'ZZZ', 'name' => 'Mentions fpt in name', 'exchange' => 'HNX']);   // name only
    }

    #[Group('stockSearch')]
    public function test_results_are_ranked_exact_then_prefix_then_contains_then_name_only(): void
    {
        $this->seed3();

        $this->assertSame(['FPT', 'FPTS', 'AFPT', 'ZZZ'], $this->symbols('fpt'));
    }

    #[Group('stockSearch')]
    public function test_only_the_columns_the_dropdown_needs_are_returned(): void
    {
        $this->seed3();

        $row = ($this->app->make(StockRepository::class))->searchSymbols('FPT')[0];

        $this->assertEqualsCanonicalizing(['symbol', 'name', 'exchange'], array_keys($row));
    }

    #[Group('stockSearch')]
    public function test_wildcards_typed_by_the_user_are_literal_and_no_match_is_an_empty_list(): void
    {
        $this->seed3();

        $this->assertSame([], $this->symbols('%'));
        $this->assertSame([], $this->symbols('_'));
        $this->assertSame([], $this->symbols('nomatchatall'));
    }

    #[Group('stockSearch')]
    public function test_results_are_capped_at_twenty(): void
    {
        foreach (range(1, 30) as $i) {
            StockSymbol::create(['symbol' => 'AB' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'name' => "Co {$i}", 'exchange' => 'HSX']);
        }

        $this->assertCount(20, $this->symbols('AB'));
    }
}
