<?php

namespace App\Frontend\Controllers;

use App\Frontend\Services\FundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FundController extends Controller
{
    public function __construct(private readonly FundService $service) {}

    /** Catalog: filter by type / management company / name, sort by any return window. */
    public function index(Request $request): View
    {
        return view('funds.index', $this->service->catalog($request->query()));
    }

    /** Side-by-side comparison of up to FundService::MAX_COMPARE funds (?codes=A,B,C). */
    public function compare(Request $request): View
    {
        $codes = $this->service->parseCodes($request->query('codes'));

        return view('funds.compare', [
            'funds'  => $this->service->compareFunds($codes),
            'picker' => $this->service->pickerList(),
            'max'    => FundService::MAX_COMPARE,
        ]);
    }

    public function show(string $code): View
    {
        $fund = $this->service->find($code) ?? abort(404);

        return view('funds.show', ['fund' => $fund]);
    }

    /** AJAX: NAV history + holdings + per-window stats for one fund (cached for hours). */
    public function detail(string $code): JsonResponse
    {
        $result = $this->service->detail($code);

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'error' => $result['error']], ($result['not_found'] ?? false) ? 404 : 502);
        }

        return response()->json(['success' => true] + $result);
    }
}
