<?php

namespace App\Frontend\Controllers;

use App\Frontend\Services\CompanyProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $symbol = CompanyProfileService::normalizeSymbol($symbol) ?? abort(404);

        $profile = $this->service->find($symbol);

        return view('company.show', [
            'symbol'   => $symbol,
            'company'  => $profile ? $this->service->present($profile) : null,
            'notFound' => ! $profile && $this->service->isKnownMissing($symbol),
        ]);
    }

    /** AJAX: fetch + cache the profile now. `force=1` is the manual "refresh" button (rate-limited). */
    public function load(Request $request, string $symbol): JsonResponse
    {
        $symbol = CompanyProfileService::normalizeSymbol($symbol) ?? abort(404);

        $result = $this->service->load($symbol, $request->boolean('force'));

        if (isset($result['error'])) {
            $status = match (true) {
                (bool) ($result['not_found'] ?? false) => 404,
                (bool) ($result['cooldown'] ?? false) => 429,
                (bool) ($result['busy'] ?? false) => 503,
                default => 502,
            };

            return response()->json(['success' => false, 'error' => $result['error']], $status);
        }

        return response()->json([
            'success'   => true,
            'synced_at' => $result['profile']->synced_at?->toIso8601String(),
        ]);
    }
}
