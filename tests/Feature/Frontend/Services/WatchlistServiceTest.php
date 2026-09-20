<?php

namespace Tests\Feature\Frontend\Services;

use App\Frontend\Repositories\MarketSnapshotRepository;
use App\Frontend\Repositories\StockRepository;
use App\Frontend\Repositories\WatchlistRepository;
use App\Frontend\Services\MarketOverviewService;
use App\Frontend\Services\StockService;
use App\Frontend\Services\WatchlistService;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Models\StockSymbol;
use App\Models\User;
use App\Models\WatchlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\BuildsMarketPayload;
use Tests\TestCase;

class WatchlistServiceTest extends TestCase
{
    use RefreshDatabase, BuildsMarketPayload;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->user = User::factory()->create();
        foreach (['FPT' => 'FPT Corp', 'NVB' => 'Ngân hàng Quốc Dân', 'HPG' => 'Hòa Phát', 'ACB' => 'Á Châu', 'ZZZ' => 'Không có giá'] as $sym => $name) {
            StockSymbol::create(['symbol' => $sym, 'name' => $name, 'exchange' => 'HSX']);
        }
    }

    private function service(): WatchlistService
    {
        $market = new MarketOverviewService(new MarketSnapshotRepository);

        return new WatchlistService(new WatchlistRepository, $market, new StockRepository(Mockery::mock(StockService::class)));
    }

    #[Group('watchlist')]
    public function test_normalize_accepts_tickers_only(): void
    {
        $this->assertSame('FPT', WatchlistService::normalize(' fpt '));
        $this->assertSame('E1VFVN30', WatchlistService::normalize('e1vfvn30'));
        $this->assertNull(WatchlistService::normalize('A'));
        $this->assertNull(WatchlistService::normalize('FPT; DROP'));
        $this->assertNull(WatchlistService::normalize(''));
    }

    #[Group('watchlist')]
    public function test_add_is_idempotent_and_validates_the_symbol(): void
    {
        $s = $this->service();

        $first = $s->add($this->user->id, 'fpt');
        $again = $s->add($this->user->id, 'FPT');

        $this->assertTrue($first['ok']);
        $this->assertTrue($first['added']);
        $this->assertFalse($again['added']);
        $this->assertSame(1, WatchlistItem::count());

        $this->assertFalse($s->add($this->user->id, 'NOPE1')['ok']);             // not a listed symbol
        $this->assertSame(422, $s->add($this->user->id, '!!')['status']);         // not even a ticker shape
        $this->assertSame(1, WatchlistItem::count());
    }

    #[Group('watchlist')]
    public function test_the_list_is_capped_per_user(): void
    {
        $s = $this->service();
        for ($i = 0; $i < WatchlistService::MAX_ITEMS; $i++) {
            WatchlistItem::create(['user_id' => $this->user->id, 'symbol' => 'X' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
        }

        $r = $s->add($this->user->id, 'FPT');

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString((string) WatchlistService::MAX_ITEMS, $r['message']);
        // another user is not affected
        $this->assertTrue($s->add(User::factory()->create()->id, 'FPT')['ok']);
    }

    #[Group('watchlist')]
    public function test_remove_only_touches_the_owners_row(): void
    {
        $s = $this->service();
        $other = User::factory()->create();
        $s->add($this->user->id, 'FPT');
        $s->add($other->id, 'FPT');

        $this->assertTrue($s->remove($this->user->id, 'fpt'));
        $this->assertFalse($s->remove($this->user->id, 'FPT'));
        $this->assertTrue($s->isWatched($other->id, 'FPT'));
        $this->assertFalse($s->isWatched($this->user->id, 'FPT'));
    }

    #[Group('watchlist')]
    public function test_rows_use_live_snapshot_quotes_newest_added_first(): void
    {
        $this->seedMarketSnapshot();
        $s = $this->service();
        $s->add($this->user->id, 'FPT');
        $s->add($this->user->id, 'HPG');

        $rows = $s->rows($this->user->id);

        $this->assertSame(['HPG', 'FPT'], array_column($rows, 'symbol'));
        $this->assertSame('live', $rows[1]['source']);
        $this->assertSame(71700, $rows[1]['price']);
        $this->assertSame(-2600, $rows[1]['change']);
        $this->assertSame(-3.5, $rows[1]['percent']);
        $this->assertSame('FPT Corp', $rows[1]['name']);
        $this->assertTrue($rows[0]['at_ceiling']);           // HPG closed at its ceiling price
        $this->assertFalse($rows[1]['at_ceiling']);
        $this->assertSame('2026-09-18', $rows[1]['as_of']);
    }

    #[Group('watchlist')]
    public function test_symbols_missing_from_the_snapshot_fall_back_to_the_last_stored_close(): void
    {
        $this->seedMarketSnapshot();
        $stock = Stock::create(['symbol' => 'ACB']);
        StockPrice::create(['stock_id' => $stock->id, 'date' => '2026-09-10', 'open' => 22, 'high' => 22, 'low' => 22, 'close' => 22.0, 'volume' => 1]);
        StockPrice::create(['stock_id' => $stock->id, 'date' => '2026-09-11', 'open' => 22, 'high' => 22, 'low' => 22, 'close' => 22.5, 'volume' => 1]);
        $s = $this->service();
        $s->add($this->user->id, 'ACB');
        $s->add($this->user->id, 'ZZZ');

        $rows = collect($s->rows($this->user->id))->keyBy('symbol');

        $this->assertSame('eod', $rows['ACB']['source']);
        $this->assertSame(22500, $rows['ACB']['price']);                // feed thousands -> whole VND
        $this->assertSame(500, $rows['ACB']['change']);
        $this->assertEqualsWithDelta(2.27, $rows['ACB']['percent'], 0.01);
        $this->assertSame('2026-09-11', $rows['ACB']['as_of']);
        $this->assertNull($rows['ZZZ']['price']);                        // no data anywhere: shown, but without a price
        $this->assertNull($rows['ZZZ']['source']);
    }

    #[Group('watchlist')]
    public function test_rows_can_be_limited_and_an_empty_list_costs_nothing(): void
    {
        $s = $this->service();
        $this->assertSame([], $s->rows($this->user->id));

        $s->add($this->user->id, 'FPT');
        $s->add($this->user->id, 'HPG');
        $s->add($this->user->id, 'NVB');

        $this->assertCount(2, $s->rows($this->user->id, 2));
    }
}
