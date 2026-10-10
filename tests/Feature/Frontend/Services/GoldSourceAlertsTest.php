<?php

namespace Tests\Feature\Frontend\Services;

use App\Frontend\Interfaces\ExchangeRateRepositoryInterface;
use App\Frontend\Repositories\GoldPriceRepository;
use App\Frontend\Services\GoldPriceService;
use App\Models\GoldPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * The gold page must say when a source is not reaching us. BTMC was dead for three weeks (vnstock asked an http address that stopped
 * answering) while the page kept showing its 20 September quotes as today's, because the sync only counted new rows and never kept
 * the per-source errors the script reports. Only the Python boundary is stubbed.
 */
class GoldSourceAlertsTest extends TestCase
{
    use RefreshDatabase;

    private const VN = 'Asia/Ho_Chi_Minh';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Queue::fake();
    }

    private function service(?array $scriptOutput = null): GoldPriceService
    {
        $rates = Mockery::mock(ExchangeRateRepositoryInterface::class);
        $rates->shouldReceive('getLatestRate')->with('USD')->andReturn(null);

        $mock = Mockery::mock(GoldPriceService::class, [new GoldPriceRepository, $rates])->makePartial()->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('runScript')->andReturn($scriptOutput);

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

    private function btmc(Carbon $at): GoldPrice
    {
        return $this->quote([
            'source' => 'BTMC', 'metal' => 'gold', 'product' => 'NHẪN TRÒN TRƠN (Vàng BTMC)', 'branch' => '', 'purity' => '999.9',
            'buy_price' => 143_600_000, 'sell_price' => 147_600_000, 'quoted_at' => $at, 'synced_at' => $at,
        ]);
    }

    /** What the script prints: SJC alive, BTMC reported in `errors` (the shape of the real outage). */
    private function scriptWithBtmcDown(): array
    {
        return [
            'fetched_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'sjc' => [['product' => 'Vàng SJC 1L, 10L, 1KG', 'branch' => 'Hồ Chí Minh', 'buy' => 144_600_000, 'sell' => 147_600_000]],
            'btmc' => [], 'world_usd_oz' => null,
            'errors' => ['btmc' => "HTTPConnectionPool(host='api.btmc.vn', port=80): Max retries exceeded"], 'warnings' => [],
        ];
    }

    private function messages(array $page): string
    {
        return implode("\n", array_column($page['alerts'], 'message'));
    }

    private function fmt(Carbon $t): string
    {
        return $t->copy()->timezone(self::VN)->format('H:i d/m/Y');
    }

    #[Group('goldPrice')]
    public function test_healthy_data_and_no_recorded_failure_show_no_alert(): void
    {
        $this->quote();
        $this->btmc(now()->subHours(2));

        $this->assertSame([], $this->service()->page()['alerts']);
    }

    #[Group('goldPrice')]
    public function test_a_failed_btmc_in_the_last_sync_is_announced_with_the_time_of_the_prices_still_shown(): void
    {
        $old = now()->subDays(20)->startOfMinute();
        $this->btmc($old);

        $this->service($this->scriptWithBtmcDown())->sync();
        $page = $this->service()->page();

        $this->assertSame(['btmc'], array_column($page['alerts'], 'source'));
        $this->assertStringContainsString('không lấy được giá Bảo Tín Minh Châu', $this->messages($page));
        $this->assertStringContainsString('số đã lưu lúc '.$this->fmt($old), $this->messages($page), 'the old numbers are dated, not passed off as today');
        $this->assertStringContainsString($this->fmt(now()), $this->messages($page), 'and so is the failed attempt');
    }

    #[Group('goldPrice')]
    public function test_a_later_successful_sync_clears_the_alert(): void
    {
        $this->btmc(now()->subDays(20));
        $this->service($this->scriptWithBtmcDown())->sync();
        $this->assertNotSame([], $this->service()->page()['alerts']);

        $ok = $this->scriptWithBtmcDown();
        $ok['errors'] = [];
        $ok['btmc'] = [['metal' => 'gold', 'product' => 'NHẪN TRÒN TRƠN (Vàng BTMC)', 'purity' => '999.9', 'buy' => 143_600_000, 'sell' => 147_600_000, 'unit' => 'luong', 'quoted_at' => now()->utc()->format('Y-m-d\TH:i:s\Z')]];
        $this->service($ok)->sync();

        $this->assertSame([], $this->service()->page()['alerts']);
    }

    #[Group('goldPrice')]
    public function test_a_failed_sjc_is_announced_too(): void
    {
        $this->quote(['quoted_at' => now()->subDays(2)->startOfMinute()]);
        $script = $this->scriptWithBtmcDown();
        $script['sjc'] = [];
        $script['errors'] = ['sjc' => 'SJC returned no data'];
        $script['btmc'] = [['metal' => 'gold', 'product' => 'NHẪN TRÒN TRƠN (Vàng BTMC)', 'purity' => '999.9', 'buy' => 143_600_000, 'sell' => 147_600_000, 'unit' => 'luong', 'quoted_at' => now()->utc()->format('Y-m-d\TH:i:s\Z')]];
        $this->service($script)->sync();

        $page = $this->service()->page();

        $this->assertSame(['sjc'], array_column($page['alerts'], 'source'));
        $this->assertStringContainsString('không lấy được giá vàng SJC', $this->messages($page));
    }

    #[Group('goldPrice')]
    public function test_when_no_sync_has_run_for_a_day_the_scheduler_is_blamed_and_old_per_source_errors_are_not_repeated(): void
    {
        $this->btmc(now()->subHours(2));
        $this->service($this->scriptWithBtmcDown())->sync();
        $syncedAt = now()->copy();

        $this->travel(GoldPriceService::SYNC_MAX_AGE_HOURS + 1)->hours();
        $page = $this->service()->page();

        $this->assertSame(['sync'], array_column($page['alerts'], 'source'), 'the failure of a day-old run says nothing about the source today');
        $this->assertStringContainsString('chưa đồng bộ giá vàng từ '.$this->fmt($syncedAt), $this->messages($page));
    }

    #[Group('goldPrice')]
    public function test_a_sync_inside_the_window_is_not_reported_as_missing(): void
    {
        $this->quote();
        $ok = $this->scriptWithBtmcDown();
        $ok['errors'] = [];
        $this->service($ok)->sync();

        $this->travel(GoldPriceService::SYNC_MAX_AGE_HOURS - 1)->hours();

        $this->assertNotContains('sync', array_column($this->service()->page()['alerts'], 'source'));
    }

    #[Group('goldPrice')]
    public function test_btmc_without_a_publication_for_three_days_is_flagged_even_with_no_recorded_failure(): void
    {
        $at = now()->subHours(GoldPriceService::BTMC_MAX_AGE_HOURS + 2)->startOfMinute();
        $this->quote();
        $this->btmc($at);

        $page = $this->service()->page();

        $this->assertSame(['btmc'], array_column($page['alerts'], 'source'));
        $this->assertStringContainsString($this->fmt($at), $this->messages($page));
        $this->assertStringContainsString('hơn 3 ngày chưa có giá mới', $this->messages($page));
    }

    #[Group('goldPrice')]
    public function test_a_weekend_of_silence_is_not_an_alarm(): void
    {
        $this->quote();
        $this->btmc(now()->subHours(GoldPriceService::BTMC_MAX_AGE_HOURS - 12));

        $this->assertSame([], $this->service()->page()['alerts'], 'BTMC may stay quiet from Friday evening to Monday morning');
    }

    #[Group('goldPrice')]
    public function test_a_run_that_returned_nothing_is_not_recorded_as_a_source_failure(): void
    {
        // a refused or killed run (null) says nothing about the sources: no false alarm, the age rules cover a lasting problem
        $this->quote();
        $this->btmc(now()->subHours(2));

        $this->service(null)->sync();

        $this->assertNull(Cache::get(GoldPriceService::STATUS_CACHE_KEY));
        $this->assertSame([], $this->service()->page()['alerts']);
    }

    #[Group('goldPrice')]
    public function test_the_status_survives_in_the_cache_with_the_reasons_for_the_admin(): void
    {
        $this->service($this->scriptWithBtmcDown())->sync();

        $status = Cache::get(GoldPriceService::STATUS_CACHE_KEY);

        $this->assertSame(['btmc'], array_keys($status['errors']));
        $this->assertStringContainsString('api.btmc.vn', $status['errors']['btmc']);
        $this->assertEqualsWithDelta(now()->timestamp, Carbon::parse($status['checked_at'])->timestamp, 5);
    }
}
