<?php

namespace Tests\Feature\Backend\Controllers;

use App\Models\AiRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\SiteSettings;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Admin > Quản lý AI: every AI call is logged (with the model that answered), the kill switch / per-account block / daily quota
 * refuse before anything reaches the provider, and the page shows it all. The provider is replaced by Http::fake.
 */
class AiAdminTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'api.groq.com/openai/v1/chat/completions';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.groq.key' => 'test-key', 'services.groq.models' => 'model-a,model-b']);
        Cache::flush();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Http::fake([self::URL => Http::response(['choices' => [['message' => ['content' => 'Trả lời AI']]]])]);
    }

    private function member(): User
    {
        return User::factory()->create();
    }

    private function admin(): User
    {
        return tap(User::factory()->create(), fn ($u) => $u->assignRole(Role::ADMIN))->fresh();
    }

    private function chat(string $text = 'VN-Index là gì?')
    {
        return $this->postJson('/ai-chat', ['message' => $text, 'lang' => 'vi']);
    }

    // ── logging ─────────────────────────────────────────────────────────────

    #[Group('adminAi')]
    public function test_a_chat_answer_is_logged_with_the_model_that_gave_it_and_the_question(): void
    {
        $user = $this->member();

        $this->actingAs($user)->chat('Cổ phiếu FPT thế nào?')->assertOk();

        $row = AiRequest::sole();
        $this->assertSame($user->id, $row->user_id);
        $this->assertSame(['chat', 'ok', 'model-a'], [$row->kind, $row->status, $row->model]);
        $this->assertSame('Cổ phiếu FPT thế nào?', $row->question);
        $this->assertGreaterThanOrEqual(0, $row->duration_ms);
    }

    #[Group('adminAi')]
    public function test_a_failed_call_is_logged_as_an_error_without_a_model(): void
    {
        Http::swap(new HttpFactory);   // drop the success stub from setUp: the first matching stub wins
        Http::fake([self::URL => Http::response('boom', 500)]);

        $this->actingAs($this->member())->chat()->assertStatus(503);

        $row = AiRequest::sole();
        $this->assertSame(['error', null], [$row->status, $row->model]);
    }

    #[Group('adminAi')]
    public function test_the_weekly_prediction_is_logged_too(): void
    {
        $this->actingAs($this->member())->postJson('/ai-predict')->assertOk();

        $this->assertSame(['predict', 'ok'], [AiRequest::sole()->kind, AiRequest::sole()->status]);
    }

    #[Group('adminAi')]
    public function test_a_long_question_is_stored_cut_short(): void
    {
        $this->actingAs($this->member())->chat(str_repeat('a', 500))->assertOk();

        $this->assertSame(300, mb_strlen(AiRequest::sole()->question));
    }

    // ── the three ways to refuse ────────────────────────────────────────────

    #[Group('adminAi')]
    public function test_the_kill_switch_refuses_everything_and_nothing_reaches_the_provider(): void
    {
        SiteSettings::set('ai', ['enabled' => false, 'daily_limit' => 0]);
        $user = $this->member();

        $this->actingAs($user)->chat()->assertStatus(503)->assertJsonPath('error', true);
        $this->postJson('/ai-predict')->assertStatus(503);

        Http::assertNothingSent();
        $this->assertSame(2, AiRequest::where('status', 'refused')->count(), 'refusals are logged too, so abuse is visible');
    }

    #[Group('adminAi')]
    public function test_a_blocked_account_gets_a_403_with_a_readable_message(): void
    {
        $user = $this->member();
        $user->forceFill(['ai_blocked_at' => now()])->save();

        $this->actingAs($user)->chat()->assertStatus(403)->assertJsonPath('message', 'Tài khoản của bạn đang bị tạm khóa tính năng AI. Vui lòng liên hệ quản trị viên.');

        Http::assertNothingSent();
        // someone else is unaffected
        $this->actingAs($this->member())->chat()->assertOk();
    }

    #[Group('adminAi')]
    public function test_the_daily_quota_stops_at_the_limit_and_refused_calls_do_not_use_it_up(): void
    {
        SiteSettings::set('ai', ['enabled' => true, 'daily_limit' => 2]);
        $user = $this->member();

        $this->actingAs($user)->chat('một')->assertOk();
        $this->chat('hai')->assertOk();
        $this->chat('ba')->assertStatus(429)->assertJsonPath('error', true);
        $this->chat('bốn')->assertStatus(429);

        $this->assertSame(2, AiRequest::where('status', 'ok')->count());
        $this->assertSame(2, AiRequest::where('status', 'refused')->count());
        Http::assertSentCount(2);
    }

    #[Group('adminAi')]
    public function test_the_quota_is_per_account_and_a_limit_of_zero_means_unlimited(): void
    {
        SiteSettings::set('ai', ['enabled' => true, 'daily_limit' => 1]);
        $a = $this->member();
        $b = $this->member();

        $this->actingAs($a)->chat()->assertOk();
        $this->chat()->assertStatus(429);
        $this->flushSession();
        $this->actingAs($b)->chat()->assertOk();

        SiteSettings::set('ai', ['enabled' => true, 'daily_limit' => 0]);
        $this->chat()->assertOk();
    }

    // ── the admin page ──────────────────────────────────────────────────────

    #[Group('adminAi')]
    public function test_the_page_shows_usage_models_and_recent_questions(): void
    {
        $user = $this->member();
        $this->actingAs($user)->chat('Câu hỏi thử nghiệm')->assertOk();
        $this->flushSession();
        $this->actingAs($this->admin());

        $this->get('/admin/ai')->assertOk()
            ->assertSee('Quản lý AI')
            ->assertSee('model-a')
            ->assertSee('Câu hỏi thử nghiệm')
            ->assertSee($user->name)
            ->assertSee('Khóa AI');
    }

    #[Group('adminAi')]
    public function test_a_plain_user_cannot_open_the_page_or_use_its_actions(): void
    {
        $user = $this->member();
        $other = $this->member();

        $this->actingAs($user);
        $this->get('/admin/ai')->assertRedirect();
        $this->post('/admin/ai/settings', ['daily_limit' => 0])->assertRedirect();
        $this->post("/admin/ai/users/{$other->id}/toggle-block")->assertRedirect();

        $this->assertNull($other->fresh()->ai_blocked_at);
        $this->assertSame(['enabled' => true, 'daily_limit' => 0], SiteSettings::ai());
    }

    #[Group('adminAi')]
    public function test_the_settings_form_saves_the_switch_and_the_quota_and_validates_the_number(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/ai/settings', ['daily_limit' => 15])->assertSessionHasNoErrors();   // the unchecked switch is simply absent = off
        $this->assertSame(['enabled' => false, 'daily_limit' => 15], SiteSettings::ai());

        $this->post('/admin/ai/settings', ['enabled' => '1', 'daily_limit' => 7])->assertSessionHasNoErrors();
        $this->assertSame(['enabled' => true, 'daily_limit' => 7], SiteSettings::ai());

        foreach (['-1', '1001', 'abc', ''] as $bad) {
            $this->post('/admin/ai/settings', ['enabled' => '1', 'daily_limit' => $bad])->assertSessionHasErrors('daily_limit');
        }
        $this->assertSame(7, SiteSettings::ai()['daily_limit'], 'a refused form changes nothing');
    }

    #[Group('adminAi')]
    public function test_blocking_and_unblocking_an_account_is_one_toggle_and_is_audited(): void
    {
        $admin = $this->admin();
        $user = $this->member();
        $this->actingAs($admin);

        $this->post("/admin/ai/users/{$user->id}/toggle-block")->assertRedirect();
        $this->assertNotNull($user->fresh()->ai_blocked_at);

        $this->post("/admin/ai/users/{$user->id}/toggle-block")->assertRedirect();
        $this->assertNull($user->fresh()->ai_blocked_at);

        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'event_type' => 'admin_action', 'description' => "Khóa AI của user #{$user->id}"]);
        $this->assertDatabaseHas('activity_logs', ['description' => "Mở khóa AI của user #{$user->id}"]);
    }

    #[Group('adminAi')]
    public function test_ai_blocked_at_cannot_be_set_from_request_input(): void
    {
        $this->assertNotContains('ai_blocked_at', (new User)->getFillable());
    }

    #[Group('adminAi')]
    public function test_an_adminsupport_account_sees_numbers_but_not_names_or_emails(): void
    {
        $user = $this->member();
        $this->actingAs($user)->chat()->assertOk();
        $this->flushSession();
        $support = tap(User::factory()->create(), fn ($u) => $u->assignRole(Role::ADMIN_SUPPORT))->fresh();
        $this->actingAs($support);

        $this->get('/admin/ai')->assertOk()->assertSee("Tài khoản #{$user->id}")->assertDontSee($user->email);
    }
}
