<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Frontend\Services\GoldPriceService;
use App\Jobs\SyncGoldPricesJob;
use App\Models\GoldPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * RefreshDatabase: the page extends layouts.app (navbar queries news_categories) and reads gold_prices.
 * Wherever a request could reach Python, the service is bound to a stub first.
 */
class GoldPriceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    private function seedQuotes(): GoldPrice
    {
        $sjc = GoldPrice::create([
            'source' => 'SJC', 'metal' => 'gold', 'product' => 'Vàng SJC 1L, 10L, 1KG', 'branch' => 'Hồ Chí Minh', 'unit' => 'luong',
            'buy_price' => 144_600_000, 'sell_price' => 147_600_000, 'world_price' => 4378.0,
            'quoted_at' => now()->subMinutes(5), 'synced_at' => now()->subMinutes(5),
        ]);
        GoldPrice::create([
            'source' => 'BTMC', 'metal' => 'gold', 'product' => 'NHẪN TRÒN TRƠN (Vàng BTMC)', 'branch' => '', 'purity' => '999.9', 'unit' => 'luong',
            'buy_price' => 143_600_000, 'sell_price' => 147_600_000, 'quoted_at' => now()->subMinutes(5), 'synced_at' => now()->subMinutes(5),
        ]);
        GoldPrice::create([
            'source' => 'BTMC', 'metal' => 'silver', 'product' => 'BẠC MIẾNG 1 LƯỢNG', 'branch' => '', 'unit' => 'pack',
            'buy_price' => 2_900_000, 'sell_price' => 3_100_000, 'quoted_at' => now()->subMinutes(5), 'synced_at' => now()->subMinutes(5),
        ]);

        return $sjc;
    }

    #[Group('goldPrice')]
    public function test_page_renders_prices_tables_and_the_chart_container(): void
    {
        Queue::fake();
        $this->seedQuotes();

        $response = $this->get('/gold');

        $response->assertOk();
        $response->assertSee('Giá vàng hôm nay');
        $response->assertSee('144.600.000', false);
        $response->assertSee('147.600.000', false);
        $response->assertSee('NHẪN TRÒN TRƠN');
        $response->assertSee('BẠC MIẾNG 1 LƯỢNG');
        $response->assertSee('id="gdChart"', false);
        $response->assertSee('Áp dụng toàn quốc');
    }

    #[Group('goldPrice')]
    public function test_page_shows_a_friendly_state_when_no_data_could_be_loaded(): void
    {
        $stub = Mockery::mock(GoldPriceService::class);
        $stub->shouldReceive('page')->andReturn(['has_data' => false, 'error' => 'Nguồn dữ liệu đang lỗi']);
        $this->app->instance(GoldPriceService::class, $stub);

        $this->get('/gold')->assertOk()->assertSee('Chưa tải được giá vàng')->assertSee('Nguồn dữ liệu đang lỗi');
    }

    #[Group('goldPrice')]
    public function test_navbar_groups_exchange_rate_and_gold_under_one_market_menu(): void
    {
        Queue::fake();
        $this->seedQuotes();

        $html = $this->get('/gold')->getContent();

        $this->assertStringContainsString('Thị trường', $html);
        $this->assertStringContainsString('Tỷ giá ngoại tệ', $html);
        $this->assertStringContainsString(route('gold.index'), $html);
        $this->assertStringContainsString('href="' . url('/exchange-rate') . '"', $html);
    }

    #[Group('goldPrice')]
    public function test_history_endpoint_returns_points_and_validates_the_id(): void
    {
        Queue::fake();
        $sjc = $this->seedQuotes();

        $this->getJson("/gold/history/{$sjc->id}?range=7D")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('range', '7D')
            ->assertJsonPath('points.0.buy', 144_600_000);

        $this->getJson('/gold/history/99999')->assertNotFound()->assertJsonPath('success', false);
        $this->getJson('/gold/history/abc')->assertNotFound();   // whereNumber
    }

    #[Group('goldPrice')]
    public function test_refresh_returns_the_result_message(): void
    {
        $stub = Mockery::mock(GoldPriceService::class);
        $stub->shouldReceive('refresh')->once()->andReturn(['count' => 3, 'warnings' => []]);
        $this->app->instance(GoldPriceService::class, $stub);

        $this->postJson('/gold/refresh')->assertOk()->assertJsonPath('success', true)->assertJsonPath('message', 'Đã cập nhật 3 mức giá mới.');
    }

    #[Group('goldPrice')]
    public function test_refresh_says_when_nothing_changed(): void
    {
        $stub = Mockery::mock(GoldPriceService::class);
        $stub->shouldReceive('refresh')->once()->andReturn(['count' => 0, 'warnings' => []]);
        $this->app->instance(GoldPriceService::class, $stub);

        $this->postJson('/gold/refresh')->assertOk()->assertJsonPath('message', 'Giá chưa thay đổi so với lần cập nhật trước.');
    }

    #[Group('goldPrice')]
    public function test_refresh_maps_cooldown_to_429(): void
    {
        $stub = Mockery::mock(GoldPriceService::class);
        $stub->shouldReceive('refresh')->once()->andReturn(['error' => 'Vừa cập nhật', 'cooldown' => true]);
        $this->app->instance(GoldPriceService::class, $stub);

        $this->postJson('/gold/refresh')->assertStatus(429)->assertJsonPath('success', false);
    }

    #[Group('goldPrice')]
    public function test_refresh_maps_a_source_failure_to_502(): void
    {
        $stub = Mockery::mock(GoldPriceService::class);
        $stub->shouldReceive('refresh')->once()->andReturn(['error' => 'Không lấy được giá vàng']);
        $this->app->instance(GoldPriceService::class, $stub);

        $this->postJson('/gold/refresh')->assertStatus(502)->assertJsonPath('message', 'Không lấy được giá vàng');
    }

    #[Group('goldPrice')]
    public function test_the_sync_job_delegates_to_the_service_and_throws_on_error_so_the_queue_retries(): void
    {
        $ok = Mockery::mock(GoldPriceService::class);
        $ok->shouldReceive('sync')->once()->andReturn(['count' => 2, 'warnings' => []]);
        (new SyncGoldPricesJob)->handle($ok);

        $bad = Mockery::mock(GoldPriceService::class);
        $bad->shouldReceive('sync')->once()->andReturn(['error' => 'down']);
        $this->expectException(\RuntimeException::class);
        (new SyncGoldPricesJob)->handle($bad);
    }
}
