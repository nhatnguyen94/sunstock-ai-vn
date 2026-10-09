<?php

namespace Tests\Feature\Support;

use App\Support\TransformerResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * The central response class: status codes, stock messages, the JSON envelope (and the legacy keys riding on it), views, redirects, aborts.
 */
class TransformerResponseTest extends TestCase
{
    private function body(JsonResponse $response): array
    {
        return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    // ── what it is ──────────────────────────────────────────────────────────

    #[Group('transformerResponse')]
    public function test_it_is_a_response_so_a_controller_can_return_it_directly(): void
    {
        $r = TransformerResponse::success();

        $this->assertInstanceOf(JsonResponse::class, $r);
        $this->assertInstanceOf(BaseResponse::class, $r);
        $this->assertStringContainsString('application/json', $r->headers->get('Content-Type'));
    }

    #[Group('transformerResponse')]
    public function test_the_status_constants_are_the_http_numbers(): void
    {
        $expected = ['OK' => 200, 'CREATED' => 201, 'ACCEPTED' => 202, 'NO_CONTENT' => 204, 'BAD_REQUEST' => 400, 'UNAUTHORIZED' => 401, 'FORBIDDEN' => 403,
            'NOT_FOUND' => 404, 'UNPROCESSABLE_ENTITY' => 422, 'TOO_MANY_REQUESTS' => 429, 'INTERNAL_SERVER_ERROR' => 500, 'BAD_GATEWAY' => 502, 'SERVICE_UNAVAILABLE' => 503];

        foreach ($expected as $name => $number) {
            $this->assertSame($number, constant(TransformerResponse::class.'::HTTP_'.$name), "HTTP_{$name}");
        }
    }

    // ── the envelope ────────────────────────────────────────────────────────

    #[Group('transformerResponse')]
    public function test_success_and_created_and_accepted_have_the_envelope_and_the_right_code(): void
    {
        $ok = TransformerResponse::success('Xong', ['a' => 1]);
        $this->assertSame(200, $ok->getStatusCode());
        $this->assertSame(['success' => true, 'code' => 200, 'message' => 'Xong', 'data' => ['a' => 1]], $this->body($ok));

        $created = TransformerResponse::created();
        $this->assertSame([201, true, TransformerResponse::CREATE_SUCCESSFULLY_MESSAGE], [$created->getStatusCode(), $this->body($created)['success'], $this->body($created)['message']]);

        $this->assertSame(202, TransformerResponse::accepted()->getStatusCode());
    }

    #[Group('transformerResponse')]
    #[DataProvider('failures')]
    public function test_every_failure_shortcut_has_its_code_its_stock_message_and_success_false(string $method, int $code, string $message): void
    {
        $r = TransformerResponse::$method();
        $body = $this->body($r);

        $this->assertSame($code, $r->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertSame($code, $body['code']);
        $this->assertSame($message, $body['message']);
        $this->assertSame([], $body['data']);
    }

    public static function failures(): array
    {
        return [
            'bad request' => ['badRequest', 400, TransformerResponse::BAD_REQUEST_MESSAGE],
            'unauthorized' => ['unauthorized', 401, TransformerResponse::UNAUTHORIZED_MESSAGE],
            'forbidden' => ['forbidden', 403, TransformerResponse::FORBIDDEN_MESSAGE],
            'not found' => ['notFound', 404, TransformerResponse::NOT_FOUND_MESSAGE],
            'unprocessable' => ['unprocessable', 422, TransformerResponse::VALIDATION_ERROR_MESSAGE],
            'too many' => ['tooManyRequests', 429, TransformerResponse::TOO_MANY_REQUESTS_MESSAGE],
            'server error' => ['serverError', 500, TransformerResponse::INTERNAL_SERVER_ERROR_MESSAGE],
            'bad gateway' => ['badGateway', 502, TransformerResponse::BAD_GATEWAY_MESSAGE],
            'unavailable' => ['serviceUnavailable', 503, TransformerResponse::SERVICE_UNAVAILABLE_MESSAGE],
        ];
    }

    #[Group('transformerResponse')]
    public function test_failed_takes_any_code_and_message(): void
    {
        $r = TransformerResponse::failed('Lỗi riêng', 418, ['x' => 1]);

        $this->assertSame(418, $r->getStatusCode());
        $this->assertSame(['success' => false, 'code' => 418, 'message' => 'Lỗi riêng', 'data' => ['x' => 1]], $this->body($r));
    }

    #[Group('transformerResponse')]
    public function test_validation_errors_travel_in_data(): void
    {
        $r = TransformerResponse::unprocessable(errors: ['email' => ['Bắt buộc']]);

        $this->assertSame(['email' => ['Bắt buộc']], $this->body($r)['data']);
    }

    #[Group('transformerResponse')]
    public function test_too_many_requests_says_when_to_come_back_in_the_body_and_the_header(): void
    {
        $r = TransformerResponse::tooManyRequests('Chậm lại', 90);

        $this->assertSame('90', $r->headers->get('Retry-After'));
        $this->assertSame(90, $this->body($r)['retry_after']);
        $this->assertArrayNotHasKey('retry_after', $this->body(TransformerResponse::tooManyRequests()));
    }

    #[Group('transformerResponse')]
    public function test_no_content_is_a_204_with_an_empty_body(): void
    {
        $r = TransformerResponse::noContent();
        $r->prepare(Request::create('/'));

        $this->assertSame(204, $r->getStatusCode());
        $this->assertSame('', (string) $r->getContent());
    }

    // ── legacy keys ─────────────────────────────────────────────────────────

    #[Group('transformerResponse')]
    public function test_extra_keys_sit_on_top_level_and_win_a_clash_so_existing_scripts_keep_working(): void
    {
        $r = TransformerResponse::failed('Chung', 503, extra: ['error' => true, 'message' => 'Câu của script', 'login_url' => '/login']);
        $body = $this->body($r);

        $this->assertTrue($body['error']);
        $this->assertSame('/login', $body['login_url']);
        $this->assertSame('Câu của script', $body['message'], 'the legacy key wins');
        $this->assertFalse($body['success']);
        $this->assertSame(503, $body['code']);
    }

    #[Group('transformerResponse')]
    public function test_json_sends_a_payload_exactly_as_given(): void
    {
        $this->assertSame([['symbol' => 'FPT']], $this->body(TransformerResponse::json([['symbol' => 'FPT']])));
        $this->assertSame(['error' => 'x'], $this->body(TransformerResponse::json(['error' => 'x'], 400)));
        $this->assertSame(400, TransformerResponse::json([], 400)->getStatusCode());
    }

    #[Group('transformerResponse')]
    public function test_a_paginator_and_a_cookie_can_ride_along(): void
    {
        $page = new LengthAwarePaginator([['id' => 1]], 1, 10);
        $this->assertSame(1, $this->body(TransformerResponse::success('ok', $page))['data']['total']);

        $r = TransformerResponse::make(true, [], 200, 'ok', cookie: ['name' => 'seen', 'value' => '1', 'time' => 5]);
        $this->assertSame('seen', $r->headers->getCookies()[0]->getName());
    }

    // ── views, redirects, aborts ────────────────────────────────────────────

    #[Group('transformerResponse')]
    public function test_a_view_can_carry_an_explicit_status(): void
    {
        $dir = sys_get_temp_dir().'/tr-views-'.uniqid();
        mkdir($dir);
        file_put_contents($dir.'/probe.blade.php', 'Xin chào {{ $name }}');
        view()->addLocation($dir);

        $r = TransformerResponse::view('probe', ['name' => 'Sun'], 404);

        $this->assertSame(404, $r->getStatusCode());
        $this->assertSame('Xin chào Sun', $r->getContent());
        $this->assertInstanceOf(Response::class, $r);
        unlink($dir.'/probe.blade.php');
        rmdir($dir);
    }

    #[Group('transformerResponse')]
    public function test_flash_helpers_use_the_four_keys_the_layouts_read(): void
    {
        Route::get('/_t/ok', fn () => TransformerResponse::backSuccess('Đã lưu'))->middleware('web');
        Route::get('/_t/err', fn () => TransformerResponse::backError())->middleware('web');
        Route::get('/_t/warn', fn () => TransformerResponse::backWarning('Cẩn thận'))->middleware('web');

        $this->get('/_t/ok')->assertSessionHas('success', 'Đã lưu');
        $this->get('/_t/err')->assertSessionHas('error', TransformerResponse::INTERNAL_SERVER_ERROR_MESSAGE);
        $this->get('/_t/warn')->assertSessionHas('warning', 'Cẩn thận');
    }

    #[Group('transformerResponse')]
    public function test_redirect_helpers_flash_and_go_to_the_named_route(): void
    {
        Route::get('/_t/dest', fn () => 'x')->name('t.dest');
        Route::get('/_t/go', fn () => TransformerResponse::redirectSuccess('t.dest'))->middleware('web');
        Route::get('/_t/stop', fn () => TransformerResponse::redirectError('t.dest', 'Hỏng'))->middleware('web');
        app('router')->getRoutes()->refreshNameLookups();   // routes added at run time need their names indexed

        $this->get('/_t/go')->assertRedirect(route('t.dest'))->assertSessionHas('success', TransformerResponse::UPDATE_SUCCESSFULLY_MESSAGE);
        $this->get('/_t/stop')->assertRedirect(route('t.dest'))->assertSessionHas('error', 'Hỏng');
    }

    #[Group('transformerResponse')]
    public function test_negotiated_answers_json_to_a_script_and_a_redirect_to_a_form(): void
    {
        Route::post('/_t/neg', fn (Request $r) => TransformerResponse::negotiated($r, (bool) $r->input('ok'), 'Kết quả', TransformerResponse::HTTP_UNPROCESSABLE_ENTITY))->middleware('web');

        $this->postJson('/_t/neg', ['ok' => 1])->assertOk()->assertJsonPath('success', true)->assertJsonPath('message', 'Kết quả');
        $this->postJson('/_t/neg', ['ok' => 0])->assertStatus(422)->assertJsonPath('success', false);
        $this->post('/_t/neg', ['ok' => 1])->assertRedirect()->assertSessionHas('success', 'Kết quả');
        $this->post('/_t/neg', ['ok' => 0])->assertRedirect()->assertSessionHas('error', 'Kết quả');
    }

    #[Group('transformerResponse')]
    public function test_abort_helpers_throw_with_the_code_and_the_stock_message(): void
    {
        try {
            TransformerResponse::abortWith(TransformerResponse::HTTP_NOT_FOUND);
            $this->fail('did not abort');
        } catch (HttpException $e) {
            $this->assertSame([404, TransformerResponse::NOT_FOUND_MESSAGE], [$e->getStatusCode(), $e->getMessage()]);
        }

        try {
            TransformerResponse::abortIf(true, TransformerResponse::HTTP_FORBIDDEN, 'Riêng');
            $this->fail('did not abort');
        } catch (HttpException $e) {
            $this->assertSame([403, 'Riêng'], [$e->getStatusCode(), $e->getMessage()]);
        }

        TransformerResponse::abortIf(false, 404);
        TransformerResponse::abortUnless(true, 404);
        $this->expectException(HttpException::class);
        TransformerResponse::abortUnless(false, TransformerResponse::HTTP_FORBIDDEN);
    }

    #[Group('transformerResponse')]
    public function test_default_message_covers_the_codes_and_is_empty_for_the_rest(): void
    {
        $this->assertSame(TransformerResponse::FORBIDDEN_MESSAGE, TransformerResponse::defaultMessage(403));
        $this->assertSame(TransformerResponse::BAD_GATEWAY_MESSAGE, TransformerResponse::defaultMessage(502));
        $this->assertSame('', TransformerResponse::defaultMessage(418));
    }

    #[Group('transformerResponse')]
    public function test_the_stock_messages_are_all_filled_in(): void
    {
        $messages = array_filter((new ReflectionClass(TransformerResponse::class))->getConstants(), fn ($v, $k) => str_ends_with($k, '_MESSAGE'), ARRAY_FILTER_USE_BOTH);

        $this->assertGreaterThan(15, count($messages));
        foreach ($messages as $name => $text) {
            $this->assertNotSame('', trim($text), $name);
        }
    }
}
