<?php

namespace App\Backend\Controllers;

use App\Backend\Interfaces\ActivityLogRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TimelineController extends Controller
{
    public function __construct(
        protected ActivityLogRepositoryInterface $activityRepo
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('view-timeline');

        $filters = $request->only('type', 'date', 'search');
        $items   = $this->activityRepo->paginate($filters, 20);

        // Quick-filter chips: how much of each kind happened in the last 7 days
        $counts = $this->activityRepo->countByType();

        return view('backend.timeline.index', compact('items', 'counts'));
    }

    public function stats(): JsonResponse
    {
        Gate::authorize('view-timeline');

        $counts = $this->activityRepo->countByType();

        return response()->json($counts);
    }
}