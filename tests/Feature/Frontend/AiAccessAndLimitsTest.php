<?php

namespace Tests\Feature\Frontend;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * AI chat and the market prediction are for signed-in users only and limited per account. The numbers come from
 * config/ai_limits.php (AI_PREDICT_INTERVAL_MINUTES, AI_CHAT_WINDOW_MINUTES, AI_CHAT_MAX_QUESTIONS in .env).
 */
class AiAccessAndLimitsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'api.groq.com/openai/v1/chat/completions';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.groq.key' => 'test-key', 'services.groq.models' => null]);
        Cache::flush();
        Http::fake([self::URL => Http::response(['choices' => [['message' => ['content' => 'Trả lời AI']]]])]);
    }

    private function chat(string $text = 'VN-Index là gì?')
    {
        return $this->postJson('/ai-chat', ['message' => $text, 'lang' => 'vi']);
    }

    // ── sign-in required ────────────────────────────────────────────────────

    #[Group('aiLimits')]
    public function test_guests_get_a_401_with_a_readable_message_and_the_login_url_and_nothing_is_sent_to_the_provider(): void
    {
        $this->chat()->assertStatus(401)
            ->assertJsonPath('error', true)
            ->assertJsonPath('message', 'Vui lòng đăng nhập để sử dụng tính năng này.')
            ->assertJsonPath('login_url', route('login'));
        $this->postJson('/ai-predict')->assertStatus(401)->assertJsonPath('login_url', route('login'));

        Http::assertNothingSent();
    }

    #[Group('aiLimits')]
    public function test_a_browser_form_post_by_a_guest_is_redirected_to_the_login_page(): void
    {
        $this->post('/ai-chat', ['message' => 'hi'])->assertRedirect(route('login'));
    }

    #[Group('aiLimits')]
    public function test_guest_attempts_never_use_up_anybody_elses_budget(): void
    {
        foreach (range(1, 10) as $i) {
            $this->chat()->assertStatus(401);
        }

        $this->actingAs(User::factory()->create());
        $this->chat()->assertOk();
    }

    #[Group('aiLimits')]
    public function test_blocked_and_pending_accounts_cannot_use_the_ai_either(): void
    {
        foreach ([User::factory()->blocked()->create(), User::factory()->inactive()->create(), User::factory()->unverified()->create()] as $user) {
            $this->actingAs($user)->chat()->assertStatus(401);
            $this->app['auth']->forgetGuards();
            $this->flushSession();
        }

        Http::assertNothingSent();
    }

    // ── prediction: one per interval ───────────────────────────────────────

    #[Group('aiLimits')]
    public function test_the_prediction_can_be_requested_once_per_interval_by_default_fifteen_minutes(): void
    {
        $this->assertSame(15, config('ai_limits.predict.interval_minutes'));
        $this->actingAs(User::factory()->create());

        $this->postJson('/ai-predict')->assertOk()->assertJsonPath('result', 'Trả lời AI');

        $second = $this->postJson('/ai-predict');
        $second->assertStatus(429)->assertJsonPath('error', true);
        $this->assertStringContainsString('1 lần mỗi 15 phút', $second->json('message'));
        $this->assertGreaterThan(0, $second->json('retry_after'));
        $this->assertLessThanOrEqual(900, $second->json('retry_after'));
        $second->assertHeader('Retry-After');
    }

    #[Group('aiLimits')]
    public function test_the_prediction_is_available_again_after_the_interval(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/ai-predict')->assertOk();
        $this->postJson('/ai-predict')->assertStatus(429);

        $this->travel(14)->minutes();
        $this->postJson('/ai-predict')->assertStatus(429);

        $this->travel(2)->minutes();
        $this->postJson('/ai-predict')->assertOk();
    }

    #[Group('aiLimits')]
    public function test_the_prediction_interval_follows_the_configured_number_of_minutes(): void
    {
        config(['ai_limits.predict.interval_minutes' => 3]);
        $this->actingAs(User::factory()->create());

        $this->postJson('/ai-predict')->assertOk();
        $blocked = $this->postJson('/ai-predict');
        $blocked->assertStatus(429);
        $this->assertStringContainsString('1 lần mỗi 3 phút', $blocked->json('message'));

        $this->travel(4)->minutes();
        $this->postJson('/ai-predict')->assertOk();
    }

    #[Group('aiLimits')]
    public function test_one_users_prediction_does_not_use_up_another_users_turn(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/ai-predict')->assertOk();
        $this->postJson('/ai-predict')->assertStatus(429);

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->actingAs(User::factory()->create());
        $this->postJson('/ai-predict')->assertOk();
    }

    #[Group('aiLimits')]
    public function test_a_limited_prediction_never_reaches_the_provider(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/ai-predict')->assertOk();
        $this->postJson('/ai-predict')->assertStatus(429);
        $this->postJson('/ai-predict')->assertStatus(429);

        Http::assertSentCount(1);
    }

    // ── chat: N questions per window ───────────────────────────────────────

    #[Group('aiLimits')]
    public function test_chat_allows_five_questions_in_five_minutes_by_default_then_blocks(): void
    {
        $this->assertSame(5, config('ai_limits.chat.window_minutes'));
        $this->assertSame(5, config('ai_limits.chat.max_questions'));
        $this->actingAs(User::factory()->create());

        foreach (range(1, 5) as $i) {
            $this->chat("Câu hỏi số $i")->assertOk()->assertJsonPath('answer', 'Trả lời AI');
        }

        $sixth = $this->chat('Câu thứ sáu');
        $sixth->assertStatus(429)->assertJsonPath('error', true);
        $this->assertStringContainsString('tối đa 5 câu trong 5 phút', $sixth->json('message'));
        $sixth->assertHeader('Retry-After');
        Http::assertSentCount(5);
    }

    #[Group('aiLimits')]
    public function test_chat_is_available_again_once_the_window_has_passed(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (range(1, 5) as $i) {
            $this->chat()->assertOk();
        }
        $this->chat()->assertStatus(429);

        $this->travel(6)->minutes();

        $this->chat()->assertOk();
    }

    #[Group('aiLimits')]
    public function test_chat_limits_follow_the_configured_window_and_question_count(): void
    {
        config(['ai_limits.chat.window_minutes' => 2, 'ai_limits.chat.max_questions' => 3]);
        $this->actingAs(User::factory()->create());

        foreach (range(1, 3) as $i) {
            $this->chat()->assertOk();
        }
        $blocked = $this->chat();
        $blocked->assertStatus(429);
        $this->assertStringContainsString('tối đa 3 câu trong 2 phút', $blocked->json('message'));

        $this->travel(3)->minutes();
        $this->chat()->assertOk();
    }

    #[Group('aiLimits')]
    public function test_chat_and_prediction_have_separate_budgets(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (range(1, 5) as $i) {
            $this->chat()->assertOk();
        }
        $this->chat()->assertStatus(429);

        $this->postJson('/ai-predict')->assertOk();   // using up the chat budget does not touch the prediction
    }

    #[Group('aiLimits')]
    public function test_each_user_has_their_own_chat_budget(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (range(1, 5) as $i) {
            $this->chat()->assertOk();
        }
        $this->chat()->assertStatus(429);

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->actingAs(User::factory()->create());

        $this->chat()->assertOk();
    }

    #[Group('aiLimits')]
    public function test_the_limit_is_per_account_not_per_address(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        foreach (range(1, 5) as $i) {
            $this->actingAs($a)->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])->chat()->assertOk();
        }
        $this->actingAs($a)->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])->chat()->assertStatus(429);   // new address, same account

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->actingAs($b)->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])->chat()->assertOk();          // same address, new account
    }

    // ── configuration ──────────────────────────────────────────────────────

    #[Group('aiLimits')]
    public function test_the_defaults_in_the_config_file_are_15_5_and_5_and_never_drop_below_one(): void
    {
        $file = require base_path('config/ai_limits.php');

        $this->assertSame(['predict' => ['interval_minutes' => 15], 'chat' => ['window_minutes' => 5, 'max_questions' => 5]], $file);
    }

    #[Group('aiLimits')]
    public function test_the_env_example_documents_the_three_variables(): void
    {
        $example = file_get_contents(base_path('.env.example'));

        foreach (['AI_PREDICT_INTERVAL_MINUTES=15', 'AI_CHAT_WINDOW_MINUTES=5', 'AI_CHAT_MAX_QUESTIONS=5'] as $line) {
            $this->assertStringContainsString($line, $example);
        }
    }

    #[Group('aiLimits')]
    public function test_env_values_below_one_are_raised_to_one_so_the_limiter_can_never_be_switched_off_by_a_typo(): void
    {
        putenv('AI_CHAT_MAX_QUESTIONS=0');
        putenv('AI_CHAT_WINDOW_MINUTES=-4');
        putenv('AI_PREDICT_INTERVAL_MINUTES=abc');
        $_ENV['AI_CHAT_MAX_QUESTIONS'] = '0';
        $_ENV['AI_CHAT_WINDOW_MINUTES'] = '-4';
        $_ENV['AI_PREDICT_INTERVAL_MINUTES'] = 'abc';

        try {
            $file = require base_path('config/ai_limits.php');
        } finally {
            putenv('AI_CHAT_MAX_QUESTIONS');
            putenv('AI_CHAT_WINDOW_MINUTES');
            putenv('AI_PREDICT_INTERVAL_MINUTES');
            unset($_ENV['AI_CHAT_MAX_QUESTIONS'], $_ENV['AI_CHAT_WINDOW_MINUTES'], $_ENV['AI_PREDICT_INTERVAL_MINUTES']);
        }

        $this->assertSame(1, $file['chat']['max_questions']);
        $this->assertSame(1, $file['chat']['window_minutes']);
        $this->assertSame(1, $file['predict']['interval_minutes']);
    }
}
