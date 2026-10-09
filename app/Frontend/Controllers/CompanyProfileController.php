<?php

namespace App\Frontend\Controllers;

use App\Frontend\Services\CompanyProfileService;
use App\Support\TransformerResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CompanyProfileController extends Controller
{
    public function __construct(private readonly CompanyProfileService $service) {}

    /**
     * Company profile page. Cached profile => full server-rendered page instantly (a stale one
     * also queues a background refresh). No cache yet => a light shell whose JS calls load()
     * once, then reloads — so the first visit shows progress instead of a blank 4s wait.
     */
    public function show(string $symbol): View
    {
        $symbol = CompanyProfileService::normalizeSymbol($symbol) ?? TransformerResponse::abortWith(TransformerResponse::HTTP_NOT_FOUND);

        $profile = $this->service->find($symbol);

        return view('company.show', [
            'symbol' => $symbol,
            'company' => $profile ? $this->service->present($profile) : null,
            'notFound' => ! $profile && $this->service->isKnownMissing($symbol),
        ]);
    }

    /** AJAX: fetch + cache the profile now. `force=1` is the manual "refresh" button (rate-limited). */
    public function load(Request $request, string $symbol): JsonResponse
    {
        $symbol = CompanyProfileService::normalizeSymbol($symbol) ?? TransformerResponse::abortWith(TransformerResponse::HTTP_NOT_FOUND);

        // The manual "refresh" button re-runs the data source on demand: for signed-in users only
        if ($request->boolean('force') && ! Auth::check()) {
            $message = 'Vui lòng đăng nhập để làm mới dữ liệu.';

            return TransformerResponse::unauthorized($message, ['error' => $message, 'login_url' => route('login')]);
        }

        $result = $this->service->load($symbol, $request->boolean('force'));

        if (isset($result['error'])) {
            $status = match (true) {
                (bool) ($result['not_found'] ?? false) => TransformerResponse::HTTP_NOT_FOUND,
                (bool) ($result['cooldown'] ?? false) => TransformerResponse::HTTP_TOO_MANY_REQUESTS,
                (bool) ($result['busy'] ?? false) => TransformerResponse::HTTP_SERVICE_UNAVAILABLE,
                default => TransformerResponse::HTTP_BAD_GATEWAY,
            };

            return TransformerResponse::failed($result['error'], $status, extra: ['error' => $result['error']]);
        }

        return TransformerResponse::success(extra: ['synced_at' => $result['profile']->synced_at?->toIso8601String()]);
    }
}
