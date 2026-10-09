<?php

namespace App\Support;

use App\Support\TransformerResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

/**
 * The one place that knows the HTTP status codes and the stock messages of this application, and the one way to build a response.
 *
 *   • It IS a response: it extends `JsonResponse` (itself a `Response`), so a controller simply `return TransformerResponse::success(...)`
 *     and every existing `: JsonResponse` signature stays valid.
 *   • JSON envelope:  { success, code, message, data, ...extra }.  `$extra` is merged on top-level AFTER the envelope, so an endpoint whose
 *     page script already reads `answer`, `result`, `login_url`, `error`… keeps those keys unchanged (the legacy key wins on a clash).
 *   • Views, redirects and aborts get their codes and texts from here too: `view()`, `back*()`, `redirect*()`, `abortIf()`, `negotiated()`.
 *
 * No number such as 404 or 429 and no stock sentence belongs anywhere else in `app/`. Reach for a constant below; when a code or text is
 * missing, ADD it here. Status codes are re-declared (not just inherited) so this file is the visible list of the ones the project uses.
 */
class TransformerResponse extends JsonResponse
{
    // ── status codes ────────────────────────────────────────────────────────

    public const HTTP_OK = Response::HTTP_OK;

    public const HTTP_CREATED = Response::HTTP_CREATED;

    public const HTTP_ACCEPTED = Response::HTTP_ACCEPTED;

    public const HTTP_NO_CONTENT = Response::HTTP_NO_CONTENT;

    public const HTTP_BAD_REQUEST = Response::HTTP_BAD_REQUEST;

    public const HTTP_UNAUTHORIZED = Response::HTTP_UNAUTHORIZED;

    public const HTTP_FORBIDDEN = Response::HTTP_FORBIDDEN;

    public const HTTP_NOT_FOUND = Response::HTTP_NOT_FOUND;

    public const HTTP_UNPROCESSABLE_ENTITY = Response::HTTP_UNPROCESSABLE_ENTITY;

    public const HTTP_TOO_MANY_REQUESTS = Response::HTTP_TOO_MANY_REQUESTS;

    public const HTTP_INTERNAL_SERVER_ERROR = Response::HTTP_INTERNAL_SERVER_ERROR;

    public const HTTP_BAD_GATEWAY = Response::HTTP_BAD_GATEWAY;

    public const HTTP_SERVICE_UNAVAILABLE = Response::HTTP_SERVICE_UNAVAILABLE;

    // ── stock messages (what the visitor reads) ─────────────────────────────

    public const GET_SUCCESSFULLY_MESSAGE = 'Lấy dữ liệu thành công.';

    public const CREATE_SUCCESSFULLY_MESSAGE = 'Tạo thành công.';

    public const UPDATE_SUCCESSFULLY_MESSAGE = 'Cập nhật thành công.';

    public const DELETE_SUCCESSFULLY_MESSAGE = 'Xóa thành công.';

    public const ACCEPT_MESSAGE = 'Đã tiếp nhận yêu cầu.';

    public const NO_CONTENT_MESSAGE = 'Không có nội dung.';

    public const VALIDATION_ERROR_MESSAGE = 'Dữ liệu không hợp lệ.';

    public const BAD_REQUEST_MESSAGE = 'Yêu cầu không hợp lệ.';

    public const UNAUTHORIZED_MESSAGE = 'Vui lòng đăng nhập để sử dụng tính năng này.';

    public const FORBIDDEN_MESSAGE = 'Bạn không có quyền thực hiện thao tác này.';

    public const NOT_FOUND_MESSAGE = 'Không tìm thấy dữ liệu.';

    public const TOO_MANY_REQUESTS_MESSAGE = 'Bạn thao tác quá nhanh, vui lòng thử lại sau.';

    public const INTERNAL_SERVER_ERROR_MESSAGE = 'Có lỗi xảy ra, vui lòng thử lại sau.';

    public const BAD_GATEWAY_MESSAGE = 'Nguồn dữ liệu bên ngoài không phản hồi, vui lòng thử lại sau.';

    public const SERVICE_UNAVAILABLE_MESSAGE = 'Dịch vụ tạm thời không khả dụng. Vui lòng thử lại sau ít phút.';

    // ── messages of this project that several places repeat ─────────────────

    public const NO_PERMISSION_MESSAGE = 'Bạn không có quyền truy cập tính năng này.';

    public const ACCOUNT_NOT_ALLOWED_MESSAGE = 'Tài khoản của bạn hiện không được phép truy cập.';

    public const IP_BLOCKED_MESSAGE = 'Địa chỉ IP của bạn đã bị chặn.';

    public const ADMIN_ONLY_CHANGES_MESSAGE = 'Chỉ quản trị viên (admin) mới được thay đổi người dùng, vai trò và quyền hạn.';

    public const PORTFOLIO_NOT_FOUND_MESSAGE = 'Portfolio không tồn tại hoặc bạn không có quyền truy cập.';

    public const INVALID_VERIFICATION_LINK_MESSAGE = 'Link xác thực không hợp lệ.';

    // ── sentences for a thing that was created / updated / deleted: "Vai trò đã được tạo thành công!" ─

    public static function createdMessage(string $subject): string
    {
        return "{$subject} đã được tạo thành công!";
    }

    public static function updatedMessage(string $subject): string
    {
        return "{$subject} đã được cập nhật thành công!";
    }

    public static function deletedMessage(string $subject): string
    {
        return "{$subject} đã được xóa thành công!";
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  JSON
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The envelope. `$extra` is merged on top-level after it (so legacy keys such as `answer`, `error`, `login_url` survive and win a clash).
     *
     * @param  array{name: string, value: string, time?: int, path?: string, domain?: ?string, secure?: bool, httpOnly?: bool}|array  $cookie
     */
    public static function make(
        bool $success,
        array|Arrayable|Jsonable|LengthAwarePaginator|JsonResource $data = [],
        int $code = self::HTTP_OK,
        string $message = self::GET_SUCCESSFULLY_MESSAGE,
        array $extra = [],
        array $cookie = [],
    ): static {
        $body = array_merge(['success' => $success, 'code' => $code, 'message' => $message, 'data' => $data], $extra);
        $response = new static($body, $code);

        if ($cookie !== []) {
            $response->withCookie(cookie($cookie['name'], $cookie['value'], $cookie['time'] ?? 0, $cookie['path'] ?? '/', $cookie['domain'] ?? null, $cookie['secure'] ?? false, $cookie['httpOnly'] ?? true));
        }

        return $response;
    }

    /** A JSON body exactly as given (a list for an autocomplete, a keyed map for a chart…) with a status from the list above. */
    public static function json(mixed $payload, int $code = self::HTTP_OK): JsonResponse
    {
        return new JsonResponse($payload, $code);
    }

    public static function success(string $message = self::GET_SUCCESSFULLY_MESSAGE, array|Arrayable|Jsonable|LengthAwarePaginator|JsonResource $data = [], array $extra = []): static
    {
        return static::make(true, $data, self::HTTP_OK, $message, $extra);
    }

    public static function created(string $message = self::CREATE_SUCCESSFULLY_MESSAGE, array|Arrayable|Jsonable|LengthAwarePaginator|JsonResource $data = [], array $extra = []): static
    {
        return static::make(true, $data, self::HTTP_CREATED, $message, $extra);
    }

    public static function accepted(string $message = self::ACCEPT_MESSAGE, array|Arrayable|Jsonable|LengthAwarePaginator|JsonResource $data = [], array $extra = []): static
    {
        return static::make(true, $data, self::HTTP_ACCEPTED, $message, $extra);
    }

    public static function noContent(): static
    {
        return new static(null, self::HTTP_NO_CONTENT);
    }

    /** Any failure; the shortcuts below only fix the code and the stock message. */
    public static function failed(string $message, int $code = self::HTTP_BAD_REQUEST, array|Arrayable|Jsonable|LengthAwarePaginator|JsonResource $data = [], array $extra = []): static
    {
        return static::make(false, $data, $code, $message, $extra);
    }

    public static function badRequest(string $message = self::BAD_REQUEST_MESSAGE, array $extra = []): static
    {
        return static::failed($message, self::HTTP_BAD_REQUEST, [], $extra);
    }

    public static function unauthorized(string $message = self::UNAUTHORIZED_MESSAGE, array $extra = []): static
    {
        return static::failed($message, self::HTTP_UNAUTHORIZED, [], $extra);
    }

    public static function forbidden(string $message = self::FORBIDDEN_MESSAGE, array $extra = []): static
    {
        return static::failed($message, self::HTTP_FORBIDDEN, [], $extra);
    }

    public static function notFound(string $message = self::NOT_FOUND_MESSAGE, array $extra = []): static
    {
        return static::failed($message, self::HTTP_NOT_FOUND, [], $extra);
    }

    /** @param  array<string, mixed>  $errors  field => messages, the way the validator reports them */
    public static function unprocessable(string $message = self::VALIDATION_ERROR_MESSAGE, array $errors = [], array $extra = []): static
    {
        return static::failed($message, self::HTTP_UNPROCESSABLE_ENTITY, $errors, $extra);
    }

    public static function tooManyRequests(string $message = self::TOO_MANY_REQUESTS_MESSAGE, ?int $retryAfter = null, array $extra = []): static
    {
        $response = static::failed($message, self::HTTP_TOO_MANY_REQUESTS, [], $retryAfter !== null ? ['retry_after' => $retryAfter] + $extra : $extra);

        return $retryAfter !== null ? $response->withHeaders(['Retry-After' => (string) $retryAfter]) : $response;
    }

    public static function serverError(string $message = self::INTERNAL_SERVER_ERROR_MESSAGE, array $extra = []): static
    {
        return static::failed($message, self::HTTP_INTERNAL_SERVER_ERROR, [], $extra);
    }

    /** The server we depend on (KBS, Fmarket, MSN…) failed or answered nonsense. */
    public static function badGateway(string $message = self::BAD_GATEWAY_MESSAGE, array $extra = []): static
    {
        return static::failed($message, self::HTTP_BAD_GATEWAY, [], $extra);
    }

    public static function serviceUnavailable(string $message = self::SERVICE_UNAVAILABLE_MESSAGE, array $extra = []): static
    {
        return static::failed($message, self::HTTP_SERVICE_UNAVAILABLE, [], $extra);
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  Views, redirects, aborts
    // ═════════════════════════════════════════════════════════════════════════

    /** A Blade view with an explicit status (an error page that must not answer 200). */
    public static function view(string $view, array $data = [], int $code = self::HTTP_OK): Response
    {
        return response()->view($view, $data, $code);
    }

    /** Back to the previous page with a flash message; the admin layout and the public layout both read these four keys. */
    public static function back(string $level, string $message): RedirectResponse
    {
        return back()->with($level, $message);
    }

    public static function backSuccess(string $message = self::UPDATE_SUCCESSFULLY_MESSAGE): RedirectResponse
    {
        return static::back('success', $message);
    }

    public static function backError(string $message = self::INTERNAL_SERVER_ERROR_MESSAGE): RedirectResponse
    {
        return static::back('error', $message);
    }

    public static function backWarning(string $message): RedirectResponse
    {
        return static::back('warning', $message);
    }

    public static function backInfo(string $message): RedirectResponse
    {
        return static::back('info', $message);
    }

    public static function redirectSuccess(string $route, string $message = self::UPDATE_SUCCESSFULLY_MESSAGE, array $parameters = []): RedirectResponse
    {
        return redirect()->route($route, $parameters)->with('success', $message);
    }

    public static function redirectError(string $route, string $message = self::INTERNAL_SERVER_ERROR_MESSAGE, array $parameters = []): RedirectResponse
    {
        return redirect()->route($route, $parameters)->with('error', $message);
    }

    public static function redirectWarning(string $route, string $message, array $parameters = []): RedirectResponse
    {
        return redirect()->route($route, $parameters)->with('warning', $message);
    }

    public static function redirectInfo(string $route, string $message, array $parameters = []): RedirectResponse
    {
        return redirect()->route($route, $parameters)->with('info', $message);
    }

    /** Back to the form with what was typed (minus `$except`, e.g. the password fields) and a flash message. */
    public static function backWithInput(string $level, string $message, array $except = []): RedirectResponse
    {
        return back()->withInput(request()->except($except))->with($level, $message);
    }

    /** To the page the visitor was trying to reach before being sent to sign in (or `$default`), with a flash message. */
    public static function redirectIntended(string $default, string $level, string $message): RedirectResponse
    {
        return redirect()->intended($default)->with($level, $message);
    }

    /** To a path (not a named route), with a flash message. */
    public static function redirectTo(string $path, string $level, string $message): RedirectResponse
    {
        return redirect($path)->with($level, $message);
    }

    /**
     * For an action reached both by a fetch() call and by a plain form post: JSON for the first, a redirect back with a flash for the second.
     *
     * @return JsonResponse|RedirectResponse
     */
    public static function negotiated(Request $request, bool $ok, string $message, int $failCode = self::HTTP_BAD_REQUEST, array $data = [])
    {
        if ($request->expectsJson()) {
            return $ok ? static::success($message, $data) : static::failed($message, $failCode, $data);
        }

        return static::back($ok ? 'success' : 'error', $message);
    }

    /** `abort()` with the project's code and, when none is given, its stock message for that code. */
    public static function abortWith(int $code, ?string $message = null): never
    {
        abort($code, $message ?? self::defaultMessage($code));
    }

    public static function abortIf(bool $condition, int $code, ?string $message = null): void
    {
        if ($condition) {
            static::abortWith($code, $message);
        }
    }

    public static function abortUnless(bool $condition, int $code, ?string $message = null): void
    {
        static::abortIf(! $condition, $code, $message);
    }

    /** The stock sentence for a status code ('' for a code without one, so the framework's own page text is used). */
    public static function defaultMessage(int $code): string
    {
        return match ($code) {
            self::HTTP_BAD_REQUEST => self::BAD_REQUEST_MESSAGE,
            self::HTTP_UNAUTHORIZED => self::UNAUTHORIZED_MESSAGE,
            self::HTTP_FORBIDDEN => self::FORBIDDEN_MESSAGE,
            self::HTTP_NOT_FOUND => self::NOT_FOUND_MESSAGE,
            self::HTTP_UNPROCESSABLE_ENTITY => self::VALIDATION_ERROR_MESSAGE,
            self::HTTP_TOO_MANY_REQUESTS => self::TOO_MANY_REQUESTS_MESSAGE,
            self::HTTP_INTERNAL_SERVER_ERROR => self::INTERNAL_SERVER_ERROR_MESSAGE,
            self::HTTP_BAD_GATEWAY => self::BAD_GATEWAY_MESSAGE,
            self::HTTP_SERVICE_UNAVAILABLE => self::SERVICE_UNAVAILABLE_MESSAGE,
            default => '',
        };
    }
}
