<?php

namespace Tests\Feature\Providers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Horizon\Horizon;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Exercises Horizon::auth() as registered by App\Providers\HorizonServiceProvider
 * — the closure passed to Horizon::auth() is stored statically and invoked by
 * Horizon::check($request), so it can be tested directly against a manually
 * built Request without needing an actual HTTP round-trip through Horizon's
 * own (package) routes.
 *
 * Regression coverage for a real bug found while wiring this up: the app's
 * global Gate::before() hook (AppServiceProvider) resolves EVERY ability name
 * against the permissions table, so a naive `Gate::check('viewHorizon', ...)`
 * always returned false (no permission is literally named "viewHorizon") and
 * silently swallowed a real, correctly-defined 'viewHorizon' Gate — the fix
 * was to check `User::hasPermission('manage-queue')` directly, bypassing Gate
 * entirely. These tests would have caught that regression immediately.
 */
class HorizonServiceProviderTest extends TestCase
{
    private function userWithPermissions(array $permissionNames): User
    {
        $role = new Role(['name' => 'test-role']);
        $role->setRelation(
            'permissions',
            collect($permissionNames)->map(fn ($name) => new Permission(['name' => $name]))
        );

        $user = new User(['name' => 'Test User', 'email' => 't@example.com']);
        $user->setRelation('roles', collect([$role]));

        return $user;
    }

    private function requestAsUser(?User $user): Request
    {
        $request = Request::create('/horizon');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    #[Group('queueMonitor')]
    public function test_horizon_dashboard_denied_for_guest(): void
    {
        $this->assertFalse(Horizon::check($this->requestAsUser(null)));
    }

    #[Group('queueMonitor')]
    public function test_horizon_dashboard_allowed_for_user_with_manage_queue_permission(): void
    {
        $user = $this->userWithPermissions(['manage-queue']);

        $this->assertTrue(Horizon::check($this->requestAsUser($user)));
    }

    #[Group('queueMonitor')]
    public function test_horizon_dashboard_denied_for_user_without_manage_queue_permission(): void
    {
        // Has other real backend permissions, just not this one — proves the
        // check is specific to manage-queue, not "any backend permission".
        $user = $this->userWithPermissions(['manage-users', 'manage-features', 'view-timeline']);

        $this->assertFalse(Horizon::check($this->requestAsUser($user)));
    }
}
