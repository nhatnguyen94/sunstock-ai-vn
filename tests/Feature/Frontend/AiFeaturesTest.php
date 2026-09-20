<?php

namespace Tests\Feature\Frontend;

use App\Frontend\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regression for "Dịch vụ AI tạm thời không khả dụng": Groq retired every model hard-coded in AiService, so every call
 * failed — and the failure text was then cached for two hours as if it were a real prediction.
 */
class AiFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'api.groq.com/openai/v1/chat/completions';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.groq.key' => 'test-key', 'services.groq.models' => null]);
        Cache::flush();
    }

    private function ok(string $text = 'Thị trường đi ngang.'): \GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['choices' => [['message' => ['content' => $text]]]]);
    }

    private function retired(string $model): \GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['error' => ['code' => 'model_decommissioned', 'message' => "The model `$model` has been decommissioned"]], 400);
    }

    // ── service ─────────────────────────────────────────────────────────────

    #[Group('aiFeatures')]
    public function test_the_default_models_are_the_current_ones_and_no_retired_model_is_listed(): void
    {
        $models = (new AiService)->models();

        $this->assertSame('openai/gpt-oss-120b', $models[0]);
        foreach (['llama-3.3-70b-versatile', 'llama3-70b-8192', 'gemma2-9b-it'] as $retired) {
            $this->assertNotContains($retired, $models);
        }
    }

    #[Group('aiFeatures')]
    public function test_the_model_chain_can_be_overridden_from_the_environment(): void
    {
        config(['services.groq.models' => ' model-a , model-b ,, ']);

        $this->assertSame(['model-a', 'model-b'], (new AiService)->models());
    }

    #[Group('aiFeatures')]
    public function test_it_falls_through_retired_models_to_one_that_answers(): void
    {
        config(['services.groq.models' => 'old-a,old-b,good']);
        Http::fake([self::URL => Http::sequence()->push(['error' => ['code' => 'model_not_found']], 404)->push(['error' => ['code' => 'model_decommissioned']], 400)->push(['choices' => [['message' => ['content' => 'xin chào']]]])]);

        $this->assertSame('xin chào', (new AiService)->tryAsk('hi'));

        Http::assertSentCount(3);
    }

    #[Group('aiFeatures')]
    public function test_it_returns_null_when_every_model_fails_and_ask_falls_back_to_the_localised_message(): void
    {
        Http::fake([self::URL => $this->retired('x')]);
        $svc = new AiService;

        $this->assertNull($svc->tryAsk('hi'));
        $this->assertStringContainsString('không khả dụng', $svc->ask('hi'));
        $this->assertStringContainsString('unavailable', $svc->ask('hi', 'en'));
    }

    #[Group('aiFeatures')]
    public function test_a_missing_api_key_fails_fast_without_calling_the_api(): void
    {
        config(['services.groq.key' => '']);
        Http::fake();

        $this->assertNull((new AiService)->tryAsk('hi'));

        Http::assertNothingSent();
    }

    #[Group('aiFeatures')]
    public function test_empty_content_moves_on_to_the_next_model_and_think_blocks_are_stripped(): void
    {
        config(['services.groq.models' => 'a,b']);
        Http::fake([self::URL => Http::sequence()->push(['choices' => [['message' => ['content' => '']]]])->push(['choices' => [['message' => ['content' => "<think>nghĩ...</think>\nTrả lời"]]]])]);

        $this->assertSame('Trả lời', (new AiService)->tryAsk('hi'));
    }

    #[Group('aiFeatures')]
    public function test_the_request_uses_the_bearer_key_the_first_model_and_tells_the_model_todays_date(): void
    {
        Http::fake([self::URL => $this->ok()]);

        (new AiService)->tryAsk('câu hỏi');

        Http::assertSent(function (Request $r) {
            $body = $r->data();

            return $r->hasHeader('Authorization', 'Bearer test-key')
                && $body['model'] === 'openai/gpt-oss-120b'
                && str_contains($body['messages'][0]['content'], now('Asia/Ho_Chi_Minh')->format('d/m/Y'))
                && $body['messages'][1]['content'] === 'câu hỏi'
                && $body['max_tokens'] >= 2048;
        });
    }

    // ── prediction cache ────────────────────────────────────────────────────

    #[Group('aiFeatures')]
    public function test_a_failed_prediction_is_never_cached_so_the_next_click_can_succeed(): void
    {
        Http::fake([self::URL => Http::sequence()->push([], 500)->push([], 500)->push([], 500)->push([], 500)->push(['choices' => [['message' => ['content' => 'Dự đoán thật']]]])]);
        $svc = new AiService;

        $this->assertNull($svc->predictMarket('p'));
        $this->assertSame('Dự đoán thật', $svc->predictMarket('p'));
    }

    #[Group('aiFeatures')]
    public function test_a_real_prediction_is_cached_and_not_fetched_twice(): void
    {
        Http::fake([self::URL => $this->ok('Tuần này tăng nhẹ')]);
        $svc = new AiService;

        $this->assertSame('Tuần này tăng nhẹ', $svc->predictMarket('p'));
        $this->assertSame('Tuần này tăng nhẹ', $svc->predictMarket('p'));

        Http::assertSentCount(1);
    }

    // ── grounding ───────────────────────────────────────────────────────────

    private function snapshot(): array
    {
        return [
            'has_data' => true, 'trade_date' => '2026-09-18T00:00:00.000000Z',
            'indices' => [['name' => 'VN-Index', 'close' => 1815.66, 'change' => -7.11, 'percent' => -0.39]],
            'breadth' => ['advancers' => 426, 'decliners' => 308, 'unchanged' => 232, 'ceiling' => 40, 'floor' => 29],
            'liquidity' => ['value' => 22934022117000],
            'movers' => ['ALL' => ['gainers' => [['symbol' => 'NVB', 'percent' => 9.4]], 'losers' => [['symbol' => 'VPL', 'percent' => -6.9]]]],
        ];
    }

    #[Group('aiFeatures')]
    public function test_the_prediction_prompt_carries_the_real_market_snapshot_so_the_model_does_not_invent_levels(): void
    {
        $this->mock(\App\Frontend\Services\MarketOverviewService::class, fn ($m) => $m->shouldReceive('overview')->andReturn($this->snapshot()));
        Http::fake([self::URL => $this->ok('ok')]);

        app(AiService::class)->predictMarket('Dự đoán tuần này');

        Http::assertSent(function (Request $r) {
            $user = $r->data()['messages'][1]['content'];

            return str_contains($user, 'Dự đoán tuần này')
                && str_contains($user, 'VN-Index: 1815.66 điểm')
                && str_contains($user, '426 mã tăng')
                && str_contains($user, '22 934 tỷ')
                && str_contains($user, 'NVB +9.4%')
                && str_contains($user, 'VPL -6.9%');
        });
    }

    #[Group('aiFeatures')]
    public function test_without_a_snapshot_the_prompt_is_sent_as_is_and_a_broken_market_service_cannot_break_the_ai(): void
    {
        $this->mock(\App\Frontend\Services\MarketOverviewService::class, fn ($m) => $m->shouldReceive('overview')->andReturn(['has_data' => false]));
        $this->assertSame('', app(AiService::class)->marketContext());

        $this->mock(\App\Frontend\Services\MarketOverviewService::class, fn ($m) => $m->shouldReceive('overview')->andThrow(new \RuntimeException('db down')));
        $this->assertSame('', app(AiService::class)->marketContext());

        Http::fake([self::URL => $this->ok('ok')]);
        $this->assertSame('ok', app(AiService::class)->predictMarket('Dự đoán'));
        Http::assertSent(fn (Request $r) => $r->data()['messages'][1]['content'] === 'Dự đoán');
    }

    #[Group('aiFeatures')]
    public function test_the_system_prompt_forbids_inventing_figures_in_both_languages(): void
    {
        Http::fake([self::URL => $this->ok()]);
        $svc = new AiService;

        $svc->tryAsk('a', 'vi');
        $svc->tryAsk('a', 'en');

        $sent = Http::recorded()->map(fn ($p) => $p[0]->data()['messages'][0]['content'])->all();
        $this->assertStringContainsString('không bịa', $sent[0]);
        $this->assertStringContainsString('never invent', $sent[1]);
    }

    // ── endpoints ───────────────────────────────────────────────────────────

    #[Group('aiFeatures')]
    public function test_predict_endpoint_returns_the_result(): void
    {
        Http::fake([self::URL => $this->ok('Nhận định tuần')]);

        $this->postJson('/ai-predict')->assertOk()->assertJson(['result' => 'Nhận định tuần']);
    }

    #[Group('aiFeatures')]
    public function test_predict_endpoint_reports_a_503_with_a_message_instead_of_an_error_disguised_as_a_result(): void
    {
        Http::fake([self::URL => $this->retired('x')]);

        $this->postJson('/ai-predict')->assertStatus(503)->assertJsonPath('error', true)->assertJsonMissingPath('result')
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'không khả dụng'));
    }

    #[Group('aiFeatures')]
    public function test_chat_endpoint_answers_in_the_requested_language(): void
    {
        Http::fake([self::URL => $this->ok('VN-Index is the benchmark.')]);

        $this->postJson('/ai-chat', ['message' => 'What is VN-Index?', 'lang' => 'en'])->assertOk()->assertJson(['answer' => 'VN-Index is the benchmark.']);

        Http::assertSent(fn (Request $r) => str_contains($r->data()['messages'][0]['content'], 'You are Sun Stock AI'));
    }

    #[Group('aiFeatures')]
    public function test_chat_endpoint_reports_503_when_the_provider_is_down_in_the_users_language(): void
    {
        Http::fake([self::URL => $this->retired('x')]);

        $this->postJson('/ai-chat', ['message' => 'hi', 'lang' => 'en'])->assertStatus(503)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'temporarily unavailable'))
            ->assertJsonMissingPath('answer');
    }

    #[Group('aiFeatures')]
    public function test_chat_endpoint_validates_the_message(): void
    {
        Http::fake();

        $this->postJson('/ai-chat', ['message' => ''])->assertStatus(422);
        $this->postJson('/ai-chat', ['message' => str_repeat('a', 501)])->assertStatus(422);
        $this->postJson('/ai-chat', ['message' => 'ok', 'lang' => 'fr'])->assertStatus(422);

        Http::assertNothingSent();
    }

    // ── markup: the popup must not depend on globals a module cannot provide ─

    #[Group('aiFeatures')]
    public function test_the_chat_popup_has_no_inline_handlers_and_carries_the_ids_and_data_the_module_binds_to(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        preg_match('#<div id="aiChatBubble".*?<!-- Scripts -->#s', $html, $m);
        $popup = $m[0] ?? '';

        $this->assertNotSame('', $popup, 'chat popup markup not found');
        $this->assertStringNotContainsString('onclick=', $popup, 'app.js is an ES module: inline onclick cannot reach its functions');
        foreach (['aiChatOpenBtn', 'aiChatClose', 'aiChatSend', 'aiChatClear', 'aiChatInput', 'aiChatMessages', 'aiLangSelect', 'aiFlagIcon'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $popup);
        }
        $this->assertGreaterThanOrEqual(3, substr_count($popup, 'data-ai-question='));
    }

    #[Group('aiFeatures')]
    public function test_the_layout_module_wires_the_chat_and_no_source_uses_globals_for_it(): void
    {
        $app = file_get_contents(base_path('resources/frontend/js/layouts/app.js'));
        $chat = file_get_contents(base_path('resources/frontend/js/shared/ai-chat.js'));

        $this->assertStringContainsString("import { initAiChat } from '../shared/ai-chat.js'", $app);
        $this->assertStringContainsString('initAiChat();', $app);
        $this->assertStringContainsString("addEventListener('keydown'", $chat);      // not keypress: IME-safe Enter
        $this->assertStringContainsString('isComposing', $chat);
        $this->assertStringNotContainsString('innerHTML += ', $chat);                // no user text concatenated into markup
    }
}
