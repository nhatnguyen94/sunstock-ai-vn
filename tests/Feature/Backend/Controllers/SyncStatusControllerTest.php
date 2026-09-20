<?php

namespace Tests\Feature\Backend\Controllers;

use App\Frontend\Services\FundService;
use App\Frontend\Services\GoldPriceService;
use App\Models\Fund;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Covers the two sources added with the company-profile / fund-catalog features. RefreshDatabase because
 * the page counts real rows (funds, company_profiles) and gating goes through the real permission tables.
 */
class SyncStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUserWithPermissions(array $permissionNames): void
    {
        $role = Role::create(['name' => Role::WEBADMIN, 'display_name' => 'Web Admin']);
        $ids = collect($permissionNames)->map(fn ($n) => Permission::create(['name' => $n, 'display_name' => $n])->id);
        $role->permissions()->sync($ids);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        $this->actingAs($user);
    }

    #[Group('fundCatalog')]
    #[Group('companyProfile')]
    public function test_page_lists_the_company_profile_and_fund_sources_with_their_row_counts(): void
    {
        $this->actingAsUserWithPermissions(['manage-features']);
        Fund::create(['short_name' => 'DCDS', 'name' => 'x', 'type_code' => 'STOCK']);

        $response = $this->get('/admin/sync-status');

        $response->assertOk();
        $response->assertSee('Hồ sơ công ty');
        $response->assertSee('Quỹ mở (Fmarket)');
        $response->assertSee('sync:funds');
        $response->assertSee('sync:company-profiles');
    }

    #[Group('fundCatalog')]
    public function test_manual_trigger_runs_the_fund_sync_through_the_whitelist(): void
    {
        $this->actingAsUserWithPermissions(['manage-features']);
        $mock = Mockery::mock(FundService::class);
        $mock->shouldReceive('syncAll')->once()->andReturn(['count' => 68]);
        $this->app->instance(FundService::class, $mock);

        $this->postJson('/admin/sync-status/trigger/sync:funds')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    #[Group('goldPrice')]
    public function test_page_lists_the_gold_source_and_the_trigger_runs_the_gold_sync(): void
    {
        $this->actingAsUserWithPermissions(['manage-features']);

        $this->get('/admin/sync-status')->assertOk()->assertSee('Giá vàng (SJC, BTMC)')->assertSee('sync:gold-prices');

        $mock = Mockery::mock(GoldPriceService::class);
        $mock->shouldReceive('sync')->once()->andReturn(['count' => 7, 'warnings' => []]);
        $this->app->instance(GoldPriceService::class, $mock);

        $this->postJson('/admin/sync-status/trigger/sync:gold-prices')->assertOk()->assertJsonPath('success', true);
    }

    #[Group('fundCatalog')]
    #[Group('companyProfile')]
    public function test_unknown_keys_are_still_rejected_and_the_page_needs_manage_features(): void
    {
        $this->actingAsUserWithPermissions(['manage-features']);
        $this->postJson('/admin/sync-status/trigger/sync:funds;ls')->assertStatus(422);

        // Fresh app state for a user WITHOUT the permission
        $role = Role::firstOrCreate(['name' => Role::ADMIN_SUPPORT], ['display_name' => 'Support']);
        $plain = User::factory()->create();
        $plain->roles()->attach($role->id);
        $this->actingAs($plain);

        $this->get('/admin/sync-status')->assertForbidden();
    }
}
