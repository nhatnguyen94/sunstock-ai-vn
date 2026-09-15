<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Frontend\Controllers\ProfileController;
use App\Frontend\Interfaces\UserProfileRepositoryInterface;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * "Feature" because Auth::login()/Auth::user() need a booted container.
 * No database is touched: show()/edit() only call the (mocked)
 * UserProfileRepositoryInterface and return a View without rendering it
 * (rendering would hit the layout's navbar DB query — see docs/TESTING.md).
 *
 * Regression coverage for a real bug found in this session's audit:
 * accounts created via the admin panel (Backend\UserRepository::create())
 * had no UserProfile row, so findByUserId() returned null — and show()/
 * edit()'s Blade templates access $profile->username etc. Confirmed these
 * degrade to null-coalesced defaults rather than crashing; the actual crash
 * was in update() (Call to a member function update() on null), fixed by
 * switching to updateOrCreateForUser() + Rule::unique()->ignore(null) —
 * that fix could only be verified manually against the real DB (see
 * docs/HISTORY.md) since $request->validate()'s unique rule queries the
 * database directly, which the sqlite test DB can't support here.
 */
class ProfileControllerTest extends TestCase
{
    private function loginAsUser(): User
    {
        $user = new User(['name' => 'Test User', 'email' => 't@example.com']);
        $user->id = 1;
        Auth::login($user);

        return $user;
    }

    #[Group('profile')]
    public function test_show_does_not_crash_when_the_user_has_no_profile_row(): void
    {
        $this->loginAsUser();

        $repo = \Mockery::mock(UserProfileRepositoryInterface::class);
        $repo->shouldReceive('findByUserId')->once()->with(1)->andReturn(null);

        $view = (new ProfileController($repo))->show();

        $this->assertSame('profile.show', $view->getName());
        $this->assertNull($view->getData()['profile']);
    }

    #[Group('profile')]
    public function test_edit_does_not_crash_when_the_user_has_no_profile_row(): void
    {
        $this->loginAsUser();

        $repo = \Mockery::mock(UserProfileRepositoryInterface::class);
        $repo->shouldReceive('findByUserId')->once()->with(1)->andReturn(null);

        $view = (new ProfileController($repo))->edit();

        $this->assertSame('profile.edit', $view->getName());
        $this->assertNull($view->getData()['profile']);
    }

    #[Group('profile')]
    public function test_show_returns_the_existing_profile_when_present(): void
    {
        $this->loginAsUser();

        $profile = new UserProfile(['user_id' => 1, 'username' => 'existing_user']);

        $repo = \Mockery::mock(UserProfileRepositoryInterface::class);
        $repo->shouldReceive('findByUserId')->once()->with(1)->andReturn($profile);

        $view = (new ProfileController($repo))->show();

        $this->assertSame('existing_user', $view->getData()['profile']->username);
    }
}
