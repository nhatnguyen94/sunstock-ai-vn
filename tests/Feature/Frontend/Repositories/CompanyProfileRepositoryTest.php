<?php

namespace Tests\Feature\Frontend\Repositories;

use App\Frontend\Repositories\CompanyProfileRepository;
use App\Models\CompanyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class CompanyProfileRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Group('companyProfile')]
    public function test_upsert_creates_then_updates_one_row_per_symbol_and_restamps_synced_at(): void
    {
        $repo = new CompanyProfileRepository();

        $first = $repo->upsert('FPT', ['overview' => ['name' => 'old']]);
        $this->travel(2)->days();
        $second = $repo->upsert('FPT', ['overview' => ['name' => 'new']]);

        $this->assertSame(1, CompanyProfile::count());
        $this->assertSame('new', $repo->find('FPT')->data['overview']['name']);
        $this->assertTrue($second->synced_at->gt($first->synced_at));
        $this->assertNull($repo->find('HPG'));
    }

    #[Group('companyProfile')]
    public function test_stale_symbols_are_returned_oldest_first_and_limited(): void
    {
        $fresh = CompanyProfile::create(['symbol' => 'FRESH', 'data' => [], 'synced_at' => now()]);
        CompanyProfile::create(['symbol' => 'OLD', 'data' => [], 'synced_at' => now()->subDays(CompanyProfile::STALE_DAYS + 1)]);
        CompanyProfile::create(['symbol' => 'OLDEST', 'data' => [], 'synced_at' => now()->subDays(30)]);
        CompanyProfile::create(['symbol' => 'NEVER', 'data' => [], 'synced_at' => null]);

        $repo = new CompanyProfileRepository();

        $stale = $repo->staleSymbols(10);
        $this->assertNotContains('FRESH', $stale);
        $this->assertEqualsCanonicalizing(['OLD', 'OLDEST', 'NEVER'], $stale);
        $this->assertCount(2, $repo->staleSymbols(2));
        $this->assertEqualsCanonicalizing(['FRESH', 'OLD', 'OLDEST', 'NEVER'], $repo->allSymbols());
        $this->assertFalse($fresh->isStale());
    }
}
