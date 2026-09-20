<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Frontend\Controllers\ProfileController;
use App\Frontend\Interfaces\UserProfileRepositoryInterface;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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
 * docs/history/2026-09.md) since $request->validate()'s unique rule queries the
 * database directly, which the sqlite test DB can't support here.
 *
 * The storeAvatar() tests use Storage::fake('public') — pure in-memory
 * filesystem faking, no database involved — to cover the upload/replace/
 * remove/keep-existing branches added for the birthday/gender/avatar/
 * address/bio fields.
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

    #[Group('profile')]
    public function test_store_avatar_uploads_and_returns_the_new_path_when_a_file_is_given(): void
    {
        Storage::fake('public');
        $repo = \Mockery::mock(UserProfileRepositoryInterface::class);

        $request = Request::create('/profile', 'PUT');
        $request->files->set('avatar', UploadedFile::fake()->image('avatar.jpg'));

        $path = (new ProfileController($repo))->storeAvatar($request, null);

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    #[Group('profile')]
    public function test_store_avatar_deletes_the_old_file_when_replacing_it(): void
    {
        Storage::fake('public');
        $repo = \Mockery::mock(UserProfileRepositoryInterface::class);

        $oldPath = UploadedFile::fake()->image('old.jpg')->store('avatars', 'public');
        $profile = new UserProfile(['avatar' => $oldPath]);

        $request = Request::create('/profile', 'PUT');
        $request->files->set('avatar', UploadedFile::fake()->image('new.jpg'));

        $newPath = (new ProfileController($repo))->storeAvatar($request, $profile);

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
        $this->assertNotSame($oldPath, $newPath);
    }

    #[Group('profile')]
    public function test_store_avatar_keeps_the_existing_path_when_no_file_or_removal_requested(): void
    {
        Storage::fake('public');
        $repo = \Mockery::mock(UserProfileRepositoryInterface::class);
        $profile = new UserProfile(['avatar' => 'avatars/existing.jpg']);

        $path = (new ProfileController($repo))->storeAvatar(Request::create('/profile', 'PUT'), $profile);

        $this->assertSame('avatars/existing.jpg', $path);
    }

    #[Group('profile')]
    public function test_store_avatar_removes_the_file_when_remove_avatar_is_checked(): void
    {
        Storage::fake('public');
        $repo = \Mockery::mock(UserProfileRepositoryInterface::class);

        $oldPath = UploadedFile::fake()->image('old.jpg')->store('avatars', 'public');
        $profile = new UserProfile(['avatar' => $oldPath]);

        $request = Request::create('/profile', 'PUT', ['remove_avatar' => '1']);

        $path = (new ProfileController($repo))->storeAvatar($request, $profile);

        $this->assertNull($path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    #[Group('profile')]
    public function test_store_avatar_returns_null_when_there_is_no_profile_and_nothing_uploaded(): void
    {
        Storage::fake('public');
        $repo = \Mockery::mock(UserProfileRepositoryInterface::class);

        $path = (new ProfileController($repo))->storeAvatar(Request::create('/profile', 'PUT'), null);

        $this->assertNull($path);
    }
}
