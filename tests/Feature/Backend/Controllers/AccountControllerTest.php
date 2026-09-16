<?php

namespace Tests\Feature\Backend\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Real DB round-trip (RefreshDatabase) — genuinely needed: `Hash::check()`
 * against the real stored hash, and confirming the new password actually
 * persists correctly through the `password => 'hashed'` Eloquent cast (not
 * double-hashed — see App\Backend\Controllers\AccountController).
 *
 * Uses a `webadmin` role with NO permissions attached, deliberately — this
 * page is self-service for ANY backend account, not gated by manage-*.
 */
class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsBackendUser(string $currentPassword = 'old-password-123'): User
    {
        $role = Role::create(['name' => Role::WEBADMIN, 'display_name' => 'Web Admin']);
        $user = User::factory()->create(['password' => Hash::make($currentPassword)]);
        $user->roles()->attach($role->id);

        $this->actingAs($user);

        return $user;
    }

    #[Group('adminAccount')]
    public function test_edit_page_is_reachable_without_any_manage_permission(): void
    {
        $this->actingAsBackendUser();

        $response = $this->get('/admin/account');

        $response->assertOk();
        $response->assertSee('Đổi mật khẩu');
    }

    #[Group('adminAccount')]
    public function test_update_changes_the_password_when_current_password_is_correct(): void
    {
        $user = $this->actingAsBackendUser('old-password-123');

        $response = $this->put('/admin/account', [
            'current_password' => 'old-password-123',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $response->assertRedirect(route('admin.account.edit'));
        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    #[Group('adminAccount')]
    public function test_update_rejects_a_wrong_current_password(): void
    {
        $user = $this->actingAsBackendUser('old-password-123');

        $response = $this->put('/admin/account', [
            'current_password' => 'totally-wrong',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    #[Group('adminAccount')]
    public function test_update_rejects_a_mismatched_confirmation(): void
    {
        $this->actingAsBackendUser('old-password-123');

        $response = $this->put('/admin/account', [
            'current_password' => 'old-password-123',
            'password' => 'brand-new-password',
            'password_confirmation' => 'does-not-match',
        ]);

        $response->assertSessionHasErrors('password');
    }

    #[Group('adminAccount')]
    public function test_update_rejects_a_too_short_new_password(): void
    {
        $this->actingAsBackendUser('old-password-123');

        $response = $this->put('/admin/account', [
            'current_password' => 'old-password-123',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors('password');
    }
}
