<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Frontend\Services\StockService;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Models\StockSymbol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * The stock page header shows the price in the unit people read (whole VND for a stock, points for an index) with the
 * day's change, ranges and key figures; the history table is rebuilt in the browser, so the server renders skeleton
 * rows for it and the chart. The page used to print "62 VNĐ" for a 62,100 ₫ share.
 */
class StockPageHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        Cache::flush();
        Carbon::setTestNow('2026-09-20 03:00:00');   // Sunday: the last completed session is Friday 2026-09-18

        $stocks = Mockery::mock(StockService::class);
        $stocks->shouldReceive('refreshPrices')->never();
        $stocks->shouldReceive('fetchStockDataFromPython')->never();
        $this->app->instance(StockService::class, $stocks);

        StockSymbol::create(['symbol' => 'FPT', 'name' => 'CTCP FPT', 'exchange' => 'HSX', 'industry' => 'Công nghệ thông tin']);
        StockSymbol::create(['symbol' => 'UPC', 'name' => 'Công ty UPCoM', 'exchange' => 'UPCOM']);
    }

    private function bar(string $symbol, string $date, float $close, ?float $high = null, ?float $low = null, int $volume = 1_000_000, ?float $open = null): void
    {
        $stock = Stock::firstOrCreate(['symbol' => $symbol]);
        StockPrice::create(['stock_id' => $stock->id, 'date' => $date, 'open' => $open ?? $close, 'high' => $high ?? $close, 'low' => $low ?? $close, 'close' => $close, 'volume' => $volume]);
    }

    /** Two sessions up to the last completed one, in the feed's unit (thousands of VND). */
    private function fpt(): void
    {
        $this->bar('FPT', '2026-09-17', 62.7, 63.2, 62.4, 4_000_000);
        $this->bar('FPT', '2026-09-18', 62.1, 63.2, 62.1, 3_199_600, 62.7);
    }

    #[Group('stockPageUx')]
    public function test_a_stock_price_is_shown_in_whole_vnd_with_the_signed_change_and_never_as_62_vnd(): void
    {
        $this->fpt();

        $html = $this->get('/stock?symbol=FPT')->assertOk()->getContent();

        $this->assertStringContainsString('>62.100</span>', $html);
        $this->assertStringContainsString('₫', $html);
        $this->assertStringContainsString('-600', $html);
        $this->assertStringContainsString('(-0,96%)', $html);
        $this->assertStringNotContainsString('>62</span>', $html);
        $this->assertStringNotContainsString('VNĐ', $html);
        $this->assertStringContainsString('class="sq-chip is-down"', $html);
        $this->assertStringContainsString('class="sq-price is-down"', $html);
    }

    #[Group('stockPageUx')]
    public function test_the_header_names_the_company_exchange_and_industry_with_the_exchange_called_hose(): void
    {
        $this->fpt();

        $this->get('/stock?symbol=FPT')->assertOk()
            ->assertSee('CTCP FPT')
            ->assertSee('Công nghệ thông tin')
            ->assertSee('HOSE')
            ->assertDontSee('>HSX<', false);
    }

    #[Group('stockPageUx')]
    public function test_other_exchange_codes_are_shown_as_people_write_them(): void
    {
        $this->bar('UPC', '2026-09-17', 10.0);
        $this->bar('UPC', '2026-09-18', 10.5);

        $this->get('/stock?symbol=UPC')->assertOk()->assertSee('UPCoM');
    }

    #[Group('stockPageUx')]
    public function test_the_header_shows_the_ranges_volume_and_key_figures(): void
    {
        $this->fpt();

        $html = $this->get('/stock?symbol=FPT')->assertOk()->getContent();

        $this->assertStringContainsString('Biên độ phiên', $html);
        $this->assertStringContainsString('Biên độ 52 tuần', $html);
        $this->assertStringContainsString('63.200', $html);                         // day high
        $this->assertStringContainsString('3,2 tr', $html);                         // volume
        $this->assertStringContainsString('Mở cửa', $html);
        $this->assertStringContainsString('62.700', $html);                         // open / previous close
        $this->assertMatchesRegularExpression('/sq-range-bar is-down" style="--pos: [\d.]+%"/', $html);
    }

    #[Group('stockPageUx')]
    public function test_a_return_tile_without_enough_history_shows_a_dash_not_a_number(): void
    {
        $this->fpt();

        $html = $this->get('/stock?symbol=FPT')->assertOk()->getContent();

        $this->assertStringContainsString('1 năm', $html);
        $this->assertMatchesRegularExpression('/sq-tile-value is-na">—</', $html);
    }

    #[Group('stockPageUx')]
    public function test_an_index_is_shown_in_points_with_two_decimals_not_in_dong(): void
    {
        $this->bar('VNINDEX', '2026-09-17', 1749.30);
        $this->bar('VNINDEX', '2026-09-18', 1737.71);

        $html = $this->get('/stock?symbol=VNINDEX')->assertOk()->getContent();

        $this->assertStringContainsString('>1.737,71</span>', $html);
        $this->assertStringContainsString('điểm', $html);
        $this->assertStringContainsString('-11,59', $html);
        $this->assertStringContainsString('"scale":1', $html);
        $this->assertStringNotContainsString('1.737.710', $html);
    }

    #[Group('stockPageUx')]
    public function test_the_scripts_get_the_unit_so_the_chart_axis_and_table_use_the_same_numbers(): void
    {
        $this->fpt();

        $html = $this->get('/stock?symbol=FPT')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/const stockUnit = \{"scale":1000,"decimals":0,"unit":"(₫|\\\\u20ab)"\};/u', $html);
        $this->assertStringContainsString('const rawData = ', $html);
    }

    #[Group('stockPageUx')]
    public function test_the_price_element_carries_the_values_the_count_up_animation_needs(): void
    {
        $this->fpt();

        $html = $this->get('/stock?symbol=FPT')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="sqPrice"\s+data-from="62700"\s+data-to="62100"\s+data-decimals="0"/', $html);
    }

    #[Group('stockPageUx')]
    public function test_the_chart_and_the_history_table_start_as_skeletons_that_javascript_replaces(): void
    {
        $this->fpt();

        $html = $this->get('/stock?symbol=FPT')->assertOk()->getContent();

        $this->assertStringContainsString('class="sk-chart"', $html);
        $this->assertSame(10, substr_count($html, 'class="sk-row"'));
        $this->assertStringContainsString('aria-hidden="true"', $html);
        foreach (['Ngày', 'Đóng cửa', 'Thay đổi', 'Mở cửa', 'Cao nhất', 'Thấp nhất', 'Khối lượng'] as $column) {
            $this->assertStringContainsString($column, $html);
        }
        $this->assertStringNotContainsString('Tiền tệ', $html);      // the per-row currency badge column is gone
    }

    #[Group('stockPageUx')]
    public function test_the_history_table_is_new_and_the_finance_table_keeps_its_own_class(): void
    {
        $this->fpt();

        $html = $this->get('/stock?symbol=FPT')->assertOk()->getContent();

        $this->assertStringContainsString('id="priceTable"', $html);
        $this->assertStringContainsString('class="px-table"', $html);
        $this->assertStringContainsString('Giá tính bằng đồng (₫)', $html);
    }

    #[Group('stockPageUx')]
    public function test_the_action_buttons_are_all_there_and_the_follow_button_keeps_its_hook(): void
    {
        $this->fpt();

        $html = $this->get('/stock?symbol=FPT')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/class="sq-btn wl-toggle" data-watch="FPT"/', $html);
        $this->assertStringContainsString('data-watch-label', $html);
        $this->assertStringContainsString(route('portfolio.quick-add', ['symbol' => 'FPT']), $html);
        $this->assertStringContainsString(route('company.show', 'FPT'), $html);
        $this->assertStringContainsString('/stock/compare?symbols=FPT', $html);
    }

    #[Group('stockPageUx')]
    public function test_a_symbol_without_history_shows_the_notice_instead_of_an_empty_header(): void
    {
        $stocks = Mockery::mock(StockService::class);
        $stocks->shouldReceive('refreshPrices')->andReturn(['stored' => 0, 'errors' => []]);   // the one short first-load fetch finds nothing
        $this->app->instance(StockService::class, $stocks);

        $this->get('/stock?symbol=FPT')->assertOk()
            ->assertSee('FPT')
            ->assertSee('Chưa có dữ liệu để hiển thị biểu đồ hoặc bảng')
            ->assertDontSee('id="sqPrice"', false)
            ->assertDontSee('id="priceTable"', false);
    }

    #[Group('stockPageUx')]
    public function test_the_old_banner_markup_is_gone(): void
    {
        $this->fpt();

        $html = $this->get('/stock?symbol=FPT')->assertOk()->getContent();

        foreach (['stock-header', 'back-button', 'stock-symbol-badge'] as $oldClass) {
            $this->assertStringNotContainsString($oldClass, $html);
        }
    }
}
