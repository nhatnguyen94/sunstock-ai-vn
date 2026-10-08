<?php

namespace Tests\Feature\Frontend\Services;

use App\Frontend\Interfaces\ExchangeRateRepositoryInterface;
use App\Frontend\Interfaces\FundRepositoryInterface;
use App\Frontend\Interfaces\GoldPriceRepositoryInterface;
use App\Frontend\Services\MarketPulseService;
use App\Models\ExchangeRate;
use App\Models\Fund;
use App\Models\GoldPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

/** "Vàng · Tỷ giá · Quỹ": real SQL on the tables the site already fills; read-only, never starts a sync. */
class MarketPulseServiceTest extends TestCase
{
    use RefreshDatabase;

    private function build(): array
    {
        return $this->app->make(MarketPulseService::class)->build();
    }

    private function gold(string $source, int $buy, int $sell, Carbon $at, ?float $world = null, string $product = 'Vàng miếng 1L'): GoldPrice
    {
        return GoldPrice::create(['source' => $source, 'metal' => 'gold', 'product' => $product, 'branch' => 'Hồ Chí Minh', 'unit' => 'lượng', 'buy_price' => $buy, 'sell_price' => $sell, 'world_price' => $world, 'quoted_at' => $at, 'synced_at' => now()]);
    }

    private function rate(string $date, string $sell, string $buy = '25,720.00'): void
    {
        ExchangeRate::create(['currency_code' => 'USD', 'currency_name' => 'US DOLLAR', 'buy_cash' => $buy, 'buy_transfer' => $buy, 'sell' => $sell, 'date' => $date]);
    }

    private function fund(string $code, string $type, ?float $r12): void
    {
        Fund::create(['short_name' => $code, 'name' => 'QUỸ '.$code, 'type_code' => $type, 'nav' => 10_000, 'nav_change_12m' => $r12, 'fund_id_fmarket' => crc32($code)]);
    }

    // ── gold ────────────────────────────────────────────────────────────────

    #[Group('marketPulse')]
    public function test_gold_shows_the_sjc_price_and_its_change_since_the_previous_vietnam_day(): void
    {
        $yesterday = Carbon::now('Asia/Ho_Chi_Minh')->startOfDay()->subHours(3)->utc();
        $this->gold('SJC', 141_000_000, 142_500_000, $yesterday);
        $this->gold('SJC', 140_500_000, 143_500_000, now(), 4378.0);
        $this->gold('BTMC', 1, 2, now(), null, 'Vàng BTMC');

        $g = $this->build()['gold'];

        $this->assertSame('Vàng SJC', $g['name']);
        $this->assertSame(143_500_000, $g['price']);
        $this->assertSame(1_000_000, $g['change']);
        $this->assertEqualsWithDelta(0.7018, $g['percent'], 0.001);
        $this->assertSame('lượng', $g['unit']);
        $this->assertSame(4378.0, $g['world_usd']);
    }

    #[Group('marketPulse')]
    public function test_gold_without_a_previous_quote_has_no_change_and_without_any_quote_no_card(): void
    {
        $this->assertNull($this->build()['gold']);

        $this->gold('SJC', 140_500_000, 143_500_000, now());
        $g = $this->build()['gold'];

        $this->assertSame(143_500_000, $g['price']);
        $this->assertNull($g['change']);
        $this->assertNull($g['percent']);
    }

    // ── USD ─────────────────────────────────────────────────────────────────

    #[Group('marketPulse')]
    public function test_usd_is_the_latest_vietcombank_sell_rate_with_its_change_against_the_previous_day(): void
    {
        $this->rate('2026-10-07', '26,170.00');
        $this->rate('2026-10-08', '26,100.00');

        $u = $this->build()['usd'];

        $this->assertSame(26_100.0, $u['sell']);
        $this->assertSame(25_720.0, $u['buy']);
        $this->assertSame(-70.0, $u['change']);
        $this->assertEqualsWithDelta(-0.2675, $u['percent'], 0.001);
        $this->assertSame('2026-10-08', $u['date']);
    }

    #[Group('marketPulse')]
    public function test_usd_with_a_single_day_has_no_change_and_an_unreadable_rate_gives_no_card(): void
    {
        $this->assertNull($this->build()['usd']);

        $this->rate('2026-10-08', '-');
        $this->assertNull($this->build()['usd'], 'a dash is not a rate');

        ExchangeRate::query()->delete();
        $this->rate('2026-10-08', '26,100.00');
        $u = $this->build()['usd'];
        $this->assertNull($u['change']);
        $this->assertNull($u['percent']);
    }

    // ── funds ───────────────────────────────────────────────────────────────

    #[Group('marketPulse')]
    public function test_funds_are_the_three_best_equity_funds_over_twelve_months_without_young_or_other_types(): void
    {
        $this->fund('AAA', Fund::TYPE_STOCK, 12.5);
        $this->fund('BBB', Fund::TYPE_STOCK, 30.0);
        $this->fund('CCC', Fund::TYPE_STOCK, -3.0);
        $this->fund('DDD', Fund::TYPE_STOCK, 8.0);
        $this->fund('NEW', Fund::TYPE_STOCK, null);       // too young: no 12-month figure
        $this->fund('BND', Fund::TYPE_BOND, 99.0);        // not an equity fund

        $funds = $this->build()['funds'];

        $this->assertSame(['BBB', 'AAA', 'DDD'], array_column($funds, 'code'));
        $this->assertSame([30.0, 12.5, 8.0], array_column($funds, 'percent'));
        $this->assertSame('QUỸ BBB', $funds[0]['name']);
    }

    #[Group('marketPulse')]
    public function test_no_funds_gives_an_empty_list(): void
    {
        $this->assertSame([], $this->build()['funds']);
    }

    // ── isolation ───────────────────────────────────────────────────────────

    #[Group('marketPulse')]
    public function test_one_failing_part_does_not_take_the_others_down(): void
    {
        $this->rate('2026-10-08', '26,100.00');
        $gold = Mockery::mock(GoldPriceRepositoryInterface::class);
        $gold->shouldReceive('latestQuotes')->andThrow(new RuntimeException('gold table gone'));
        $service = new MarketPulseService($gold, $this->app->make(ExchangeRateRepositoryInterface::class), $this->app->make(FundRepositoryInterface::class));

        $pulse = $service->build();

        $this->assertNull($pulse['gold']);
        $this->assertSame(26_100.0, $pulse['usd']['sell']);
        $this->assertSame([], $pulse['funds']);
    }

    #[Group('marketPulse')]
    public function test_the_previous_rate_lookup_ignores_the_same_day_and_other_currencies(): void
    {
        $repo = $this->app->make(ExchangeRateRepositoryInterface::class);
        $this->rate('2026-10-07', '26,170.00');
        $this->rate('2026-10-08', '26,100.00');
        ExchangeRate::create(['currency_code' => 'EUR', 'currency_name' => 'EURO', 'buy_cash' => '1', 'buy_transfer' => '1', 'sell' => '2', 'date' => '2026-10-07']);

        $this->assertSame('26,170.00', $repo->getRateBefore('USD', '2026-10-08')->sell);
        $this->assertNull($repo->getRateBefore('USD', '2026-10-07'));
        $this->assertNull($repo->getRateBefore('JPY', '2026-10-08'));
    }
}
