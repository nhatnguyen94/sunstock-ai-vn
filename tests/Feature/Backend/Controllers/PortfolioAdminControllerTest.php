<?php

namespace Tests\Feature\Backend\Controllers;

use App\Models\Permission;
use App\Models\Portfolio;
use App\Models\PortfolioItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Admin > Portfolio used to render zeros / hard-coded placeholders (it read attributes that do not exist
 * on the model: total_value, profit_loss, items_count...) and its stats page had no view at all (500).
 */
class PortfolioAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFeatureManager(): void
    {
        $role = Role::create(['name' => Role::ADMIN_SUPPORT, 'display_name' => 'Support']);
        $role->permissions()->sync([Permission::create(['name' => 'manage-features', 'display_name' => 'x'])->id]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        $this->actingAs($user);
    }

    private function seedPortfolio(string $name, string $owner, float $buy, float $price, bool $active = true): Portfolio
    {
        $u = User::factory()->create(['name' => $owner]);
        $p = Portfolio::create(['user_id' => $u->id, 'name' => $name, 'total_invested' => 0, 'current_value' => 0, 'is_active' => $active]);
        PortfolioItem::create(['portfolio_id' => $p->id, 'stock_symbol' => 'ACB', 'stock_name' => 'ACB', 'quantity' => 100, 'buy_price' => $buy, 'current_price' => $price, 'buy_date' => '2026-06-01']);
        $p->recalculateTotals();

        return $p;
    }

    #[Group('portfolioAdmin')]
    public function test_index_shows_real_totals_and_per_portfolio_numbers(): void
    {
        $this->actingAsFeatureManager();
        $this->seedPortfolio('Dài hạn', 'An', 20000, 22000);   // +200,000
        $this->seedPortfolio('Lướt sóng', 'Bình', 50000, 40000, active: false);

        $response = $this->get('/admin/portfolios');

        $response->assertOk();
        $response->assertSee('Dài hạn')->assertSee('Lướt sóng');
        $response->assertSee('6.200.000 ₫');    // 2,200,000 + 4,000,000 total value
        $response->assertSee('1 đang hoạt động');
        $response->assertSee('2.200.000 ₫');    // a single portfolio's value column
        $response->assertSee('1 mã');           // items_count really loaded
        $response->assertDontSee('+12%');       // the old hard-coded placeholders
    }

    #[Group('portfolioAdmin')]
    public function test_search_and_status_filters_combine_correctly(): void
    {
        $this->actingAsFeatureManager();
        $this->seedPortfolio('Alpha', 'An Nguyen', 20000, 22000, active: true);
        $this->seedPortfolio('Alpha', 'Binh Tran', 20000, 22000, active: false);

        // name matches BOTH; the status filter must still apply (it used to be swallowed by an ungrouped OR)
        $response = $this->get('/admin/portfolios?search=Alpha&status=active');

        $response->assertOk()->assertSee('An Nguyen')->assertDontSee('Binh Tran');
        $this->get('/admin/portfolios?search=' . urlencode('binh'))->assertOk()->assertSee('Binh Tran');   // owner-name search
    }

    #[Group('portfolioAdmin')]
    public function test_detail_page_uses_the_models_real_figures(): void
    {
        $this->actingAsFeatureManager();
        $p = $this->seedPortfolio('Dài hạn', 'An', 20000, 22000);

        $this->get("/admin/portfolios/{$p->id}")->assertOk()
            ->assertSee('2.200.000 ₫')     // value
            ->assertSee('2.000.000 ₫')     // invested
            ->assertSee('+200.000 ₫');     // P&L
    }

    #[Group('portfolioAdmin')]
    public function test_stats_page_exists_and_lists_the_most_held_symbols(): void
    {
        $this->actingAsFeatureManager();
        $this->seedPortfolio('A', 'An', 20000, 22000);
        $this->seedPortfolio('B', 'Bình', 20000, 22000);

        $this->get('/admin/portfolios-stats')->assertOk()->assertSee('ACB')->assertSee('Tổng Portfolio');
    }
}
