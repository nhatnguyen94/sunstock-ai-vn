<?php

namespace App\Backend\Controllers;

use App\Backend\Services\DataQualityService;
use App\Support\TransformerResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Admin > Chất lượng dữ liệu: checks on the stock and price data, cached ten minutes, "Kiểm tra lại" recomputes. */
class DataQualityController extends Controller
{
    public function index(DataQualityService $quality): View
    {
        Gate::authorize('manage-features');

        $report = $quality->report();

        return view('backend.data-quality.index', [
            'checks' => $report['checks'],
            'generatedAt' => Carbon::parse($report['generated_at']),
            'problems' => collect($report['checks'])->where('count', '>', 0)->count(),
        ]);
    }

    public function refresh(): RedirectResponse
    {
        Gate::authorize('manage-features');

        DataQualityService::forget();

        return TransformerResponse::redirectSuccess('admin.data-quality', 'Đã kiểm tra lại.');
    }
}
