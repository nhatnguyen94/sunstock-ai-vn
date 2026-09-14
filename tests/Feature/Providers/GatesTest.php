<?php

namespace Tests\Feature\Providers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Exercises the Gates defined in AppServiceProvider::defineGates() — these
 * are registered at app boot regardless of the database, and each gate
 * closure only calls User::hasRole()/hasAnyRole(), which (since the recent
 * refactor) read the `roles` relation rather than always querying — so
 * this can run fully against in-memory User/Role instances, no DB.
 */
class GatesTest extends TestCase
{
    private function userWithRoles(array $roleNames): User
    {
        $user = new User(['name' => 'Test User', 'email' => 't@example.com']);
        $user->id = 1;
        $user->setRelation('roles', collect($roleNames)->map(fn ($name) => new Role(['name' => $name])));

        return $user;
    }

    public static function manageUsersProvider(): array
    {
        return [
            'admin allowed' => [[Role::ADMIN], true],
            'webadmin denied' => [[Role::WEBADMIN], false],
            'adminsupport denied' => [[Role::ADMIN_SUPPORT], false],
            'plain user denied' => [[Role::USER], false],
            'no roles denied' => [[], false],
        ];
    }

    #[Group('auth')]
    #[DataProvider('manageUsersProvider')]
    public function test_manage_users_gate(array $roles, bool $expected): void
    {
        $this->assertSame($expected, Gate::forUser($this->userWithRoles($roles))->allows('manage-users'));
    }

    public static function manageFeaturesProvider(): array
    {
        return [
            'admin allowed' => [[Role::ADMIN], true],
            'adminsupport allowed' => [[Role::ADMIN_SUPPORT], true],
            'webadmin denied' => [[Role::WEBADMIN], false],
            'plain user denied' => [[Role::USER], false],
        ];
    }

    #[Group('auth')]
    #[DataProvider('manageFeaturesProvider')]
    public function test_manage_features_gate(array $roles, bool $expected): void
    {
        $this->assertSame($expected, Gate::forUser($this->userWithRoles($roles))->allows('manage-features'));
    }

    public static function viewTimelineProvider(): array
    {
        return [
            'admin allowed' => [[Role::ADMIN], true],
            'webadmin allowed' => [[Role::WEBADMIN], true],
            'adminsupport allowed' => [[Role::ADMIN_SUPPORT], true],
            'plain user denied' => [[Role::USER], false],
        ];
    }

    #[Group('auth')]
    #[DataProvider('viewTimelineProvider')]
    public function test_view_timeline_gate(array $roles, bool $expected): void
    {
        $this->assertSame($expected, Gate::forUser($this->userWithRoles($roles))->allows('view-timeline'));
    }

    public static function accessBackendProvider(): array
    {
        return [
            'admin allowed' => [[Role::ADMIN], true],
            'webadmin allowed' => [[Role::WEBADMIN], true],
            'adminsupport allowed' => [[Role::ADMIN_SUPPORT], true],
            'plain user denied' => [[Role::USER], false],
            'no roles denied' => [[], false],
        ];
    }

    #[Group('auth')]
    #[DataProvider('accessBackendProvider')]
    public function test_access_backend_gate(array $roles, bool $expected): void
    {
        $this->assertSame($expected, Gate::forUser($this->userWithRoles($roles))->allows('access-backend'));
    }
}
