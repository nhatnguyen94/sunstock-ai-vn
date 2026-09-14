<?php

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\AdminAccess;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * "Feature" because Auth::login()/Auth::check() need a booted container.
 * No database is touched: guest/redirect paths are exercised via real
 * HTTP requests (they short-circuit before reaching any DB-querying
 * controller), and the pass-through path calls the middleware directly
 * with a User built via setRelation() (no DB) since it never touches
 * session/redirect machinery.
 */
class AdminAccessTest extends TestCase
{
    private function userWithRoles(array $roleNames): User
    {
        $user = new User(['name' => 'Test User', 'email' => 't@example.com']);
        $user->id = 1;
        $user->setRelation('roles', collect($roleNames)->map(fn ($name) => new Role(['name' => $name])));

        return $user;
    }

    #[Group('auth')]
    public function test_guest_is_redirected_to_admin_login(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect(route('admin.login'));
    }

    #[Group('auth')]
    public function test_authenticated_non_backend_user_is_redirected_home(): void
    {
        Auth::login($this->userWithRoles([Role::USER]));

        $response = $this->get('/admin');

        $response->assertRedirect(route('home'));
    }

    #[Group('auth')]
    public function test_admin_role_passes_through_to_the_next_middleware(): void
    {
        Auth::login($this->userWithRoles([Role::ADMIN]));

        $response = (new AdminAccess())->handle(
            Request::create('/admin'),
            fn ($req) => response('next-called')
        );

        $this->assertSame('next-called', $response->getContent());
    }

    #[Group('auth')]
    public function test_webadmin_and_adminsupport_roles_also_pass_through(): void
    {
        foreach ([Role::WEBADMIN, Role::ADMIN_SUPPORT] as $role) {
            Auth::login($this->userWithRoles([$role]));

            $response = (new AdminAccess())->handle(
                Request::create('/admin'),
                fn ($req) => response('next-called')
            );

            $this->assertSame('next-called', $response->getContent(), "role={$role} should pass through");
        }
    }

    #[Group('auth')]
    public function test_plain_user_role_does_not_pass_through(): void
    {
        Auth::login($this->userWithRoles([Role::USER]));

        $response = (new AdminAccess())->handle(
            Request::create('/admin'),
            fn ($req) => response('next-called')
        );

        $this->assertNotSame('next-called', $response->getContent());
    }
}
