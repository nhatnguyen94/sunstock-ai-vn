<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Frontend\Services\PortfolioLedgerService;
use App\Models\Etf;
use App\Models\Portfolio;
use App\Models\Stock;
use App\Models\StockPrice;
use App\Models\StockSymbol;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\BuildsMarketPayload;
use Tests\TestCase;

class PortfolioInsightsPageTest extends TestCase
{
    use RefreshDatabase, BuildsMarketPayload;

    private User $user;
    private Portfolio $portfolio;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Queue::fake();                       // the page may queue background price refreshes; never run Python here
        Carbon::setTestNow('2026-09-30 10:00:00');
        $this->seedMarketSnapshot(['quotes' => ['VCB' => [70000, 69000, 1.45, 1000, 70_000_000, 73800, 64200, 69500, 70500, 69000]]]);

        StockSymbol::create(['symbol' => 'VCB', 'name' => 'Ngân hàng Vietcombank', 'exchange' => 'HSX', 'industry' => 'Ngân hàng']);
        Etf::create(['symbol' => 'E1VFVN30', 'name' => 'Quỹ ETF DCVFMVN30', 'exchange' => 'HOSE', 'kind' => 'etf']);
        foreach (['VCB' => [50.0, 0.05], 'E1VFVN30' => [30.0, 0.02], 'VNINDEX' => [1200.0, 1.0]] as $symbol => [$start, $step]) {
            $stock = Stock::create(['symbol' => $symbol]);
            $rows = [];
            for ($i = 0, $n = 0; $i <= 260; $i++) {
                $d = now()->subDays(260 - $i);
                if ($d->isWeekend()) {
                    continue;
                }
                $c = $start + $n++ * $step;
                $rows[] = ['stock_id' => $stock->id, 'date' => $d->toDateString(), 'open' => $c, 'high' => $c, 'low' => $c, 'close' => $c, 'volume' => 1000];
            }
            StockPrice::insert($rows);
        }

        $this->user = User::factory()->create();
        $this->portfolio = Portfolio::create(['user_id' => $this->user->id, 'name' => 'Danh mục thử', 'total_invested' => 0, 'current_value' => 0, 'is_active' => true]);
        $ledger = app(PortfolioLedgerService::class);
        $ledger->trade($this->portfolio->id, $this->user->id, ['type' => 'buy', 'stock_symbol' => 'VCB', 'quantity' => 100, 'price' => 55_000, 'fee' => 0, 'traded_at' => now()->subDays(200)->toDateString()]);
        $ledger->trade($this->portfolio->id, $this->user->id, ['type' => 'buy', 'stock_symbol' => 'E1VFVN30', 'quantity' => 1000, 'price' => 31_000, 'fee' => 0, 'traded_at' => now()->subDays(100)->toDateString()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Group('portfolioInsights')]
    public function test_the_page_shows_the_sector_split_the_market_comparison_and_the_risk_card(): void
    {
        $r = $this->actingAs($this->user)->get("/portfolio/{$this->portfolio->id}");

        $r->assertOk()
            ->assertSee('Theo ngành')
            ->assertSee('So với thị trường')
            ->assertSee('VN-Index cùng kỳ')
            ->assertSee('ETF VN30 (E1VFVN30) cùng kỳ')
            ->assertSee('Cùng số tiền, cùng ngày mua bán vào VN-Index', false)
            ->assertSee('Biến động (năm hóa)')
            ->assertSee('Sụt giảm tối đa')
            ->assertSee('Beta so với VN-Index')
            ->assertSee('id="pfBenchToggle"', false)
            ->assertSee('data-bench="VNINDEX"', false)
            ->assertSee('điểm phần trăm');
    }

    #[Group('portfolioInsights')]
    public function test_holdings_show_the_etf_name_and_the_chart_data_carries_both_benchmarks_and_the_sectors(): void
    {
        $html = $this->actingAs($this->user)->get("/portfolio/{$this->portfolio->id}")->assertOk()->assertSee('DCVFMVN30')->getContent();

        preg_match('#window\.__PF__ = (\{.*?\});</script>#s', $html, $m);
        $cfg = json_decode($m[1], true);

        $this->assertSame(['VNINDEX', 'E1VFVN30'], array_keys($cfg['benchmarks']));
        $this->assertSame(count($cfg['performance']), count($cfg['benchmarks']['VNINDEX']['series']));
        $this->assertContains('Ngân hàng', $cfg['sectors']['labels']);
        $this->assertContains('Quỹ ETF / quỹ niêm yết', $cfg['sectors']['labels']);
        $this->assertEqualsWithDelta(100.0, array_sum($cfg['sectors']['series']), 0.2);
    }

    #[Group('portfolioInsights')]
    public function test_a_portfolio_without_a_ledger_still_renders_with_honest_empty_states(): void
    {
        $empty = Portfolio::create(['user_id' => $this->user->id, 'name' => 'Trống', 'total_invested' => 0, 'current_value' => 0, 'is_active' => true]);

        $this->actingAs($this->user)->get("/portfolio/{$empty->id}")->assertOk()->assertDontSee('id="pfBenchToggle"', false);
    }

    #[Group('portfolioInsights')]
    public function test_another_users_portfolio_is_still_a_404(): void
    {
        $this->actingAs(User::factory()->create())->get("/portfolio/{$this->portfolio->id}")->assertNotFound();
    }

    #[Group('portfolioInsights')]
    public function test_the_trade_form_reports_problems_in_vietnamese(): void
    {
        $r = $this->actingAs($this->user)->post("/portfolio/{$this->portfolio->id}/transactions", [
            'type' => 'buy', 'stock_symbol' => 'VCB', 'quantity' => 100, 'price' => 70_000, 'fee' => 99_999_999_999, 'traded_at' => now()->toDateString(),
        ]);

        $r->assertSessionHasErrors(['fee']);
        $this->assertSame('Phí quá lớn, hãy kiểm tra lại số lượng và giá.', session('errors')->first('fee'));

        $this->actingAs($this->user)->post("/portfolio/{$this->portfolio->id}/transactions", ['type' => 'buy'])->assertSessionHasErrors(['stock_symbol', 'quantity', 'price', 'traded_at']);
        $this->assertSame('Hãy nhập số lượng.', session('errors')->first('quantity'));
    }

    #[Group('portfolioInsights')]
    public function test_the_trade_modal_script_disables_submit_for_an_impossible_sale(): void
    {
        $js = file_get_contents(base_path('resources/frontend/js/portfolio/show.js'));

        $this->assertStringContainsString('el.submit.disabled = !!p?.blocked', $js);
    }
}
