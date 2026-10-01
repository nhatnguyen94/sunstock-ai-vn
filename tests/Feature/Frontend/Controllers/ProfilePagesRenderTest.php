<?php

namespace Tests\Feature\Frontend\Controllers;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * The profile pages are rendered for real here. The older controller tests only check which view comes back, which is
 * how /profile/edit crashed (500) for an account without a `user_profiles` row without any test noticing.
 */
class ProfilePagesRenderTest extends TestCase
{
    use RefreshDatabase;

    private function userWithoutProfile(): User
    {
        return User::factory()->create(['name' => 'Nguyễn Văn Khách', 'email' => 'noprofile@example.test']);
    }

    #[Group('profile')]
    public function test_the_edit_page_renders_for_an_account_without_a_profile_row_and_prefills_the_account_name(): void
    {
        $user = $this->userWithoutProfile();

        $html = $this->actingAs($user)->get('/profile/edit')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/name="username"[^>]*value="Nguyễn Văn Khách"/s', $html);
        $this->assertMatchesRegularExpression('/name="mobile"[^>]*value=""/s', $html);
        $this->assertSame(0, UserProfile::where('user_id', $user->id)->count(), 'opening the page must not create a row');
    }

    #[Group('profile')]
    public function test_the_show_page_renders_for_an_account_without_a_profile_row(): void
    {
        $this->actingAs($this->userWithoutProfile())->get('/profile')->assertOk()->assertSee('Chưa cập nhật');
    }

    #[Group('profile')]
    public function test_saving_the_form_creates_the_missing_profile_and_both_pages_then_show_it(): void
    {
        $user = $this->userWithoutProfile();

        $this->actingAs($user)->put('/profile', ['username' => 'khach.moi', 'mobile' => '0912345678'])->assertRedirect(route('profile.show'));

        $profile = UserProfile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('khach.moi', $profile->username);
        $this->assertSame('khach.moi', $user->fresh()->name);
        $this->actingAs($user->fresh())->get('/profile')->assertOk()->assertSee('khach.moi')->assertSee('0912345678');
        $this->assertMatchesRegularExpression('/name="username"[^>]*value="khach.moi"/s', $this->get('/profile/edit')->getContent());
    }

    #[Group('profile')]
    public function test_the_edit_page_shows_the_stored_profile_and_old_input_wins_after_a_failed_submit(): void
    {
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id, 'username' => 'dacoprofile', 'mobile' => '0987654321']);

        $html = $this->actingAs($user)->get('/profile/edit')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/name="username"[^>]*value="dacoprofile"/s', $html);
        $this->assertMatchesRegularExpression('/name="mobile"[^>]*value="0987654321"/s', $html);

        $old = $this->withSession(['_old_input' => ['username' => 'nhap-do', 'mobile' => '0900000000']])->get('/profile/edit')->getContent();
        $this->assertMatchesRegularExpression('/name="username"[^>]*value="nhap-do"/s', $old);
        $this->assertMatchesRegularExpression('/name="mobile"[^>]*value="0900000000"/s', $old);
    }

    #[Group('profile')]
    public function test_a_hostile_name_prefilled_into_the_edit_form_is_escaped(): void
    {
        $user = User::factory()->create(['name' => '"><script>alert(1)</script>']);

        $html = $this->actingAs($user)->get('/profile/edit')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;', $html);
    }
}
