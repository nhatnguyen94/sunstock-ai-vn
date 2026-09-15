<?php

namespace App\Backend\Controllers;

use App\Backend\Interfaces\QueueMonitorServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class QueueMonitorController extends Controller
{
    public function __construct(
        protected QueueMonitorServiceInterface $queueMonitorService
    ) {}

    public function index(): View
    {
        $queues = $this->queueMonitorService->getQueueStats();
        $failedJobs = $this->queueMonitorService->getFailedJobs();

        return view('backend.queue-monitor.index', compact('queues', 'failedJobs'));
    }

    /**
     * AJAX endpoint polled by the page for live queue-depth updates.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'queues' => $this->queueMonitorService->getQueueStats(),
        ]);
    }

    public function retry(string $uuid): JsonResponse
    {
        $ok = $this->queueMonitorService->retryFailedJob($uuid);

        return response()->json(
            $ok ? ['success' => true, 'message' => 'Đã đưa job vào lại queue.']
                : ['success' => false, 'message' => 'Không tìm thấy job.'],
            $ok ? 200 : 404
        );
    }

    public function destroy(string $uuid): JsonResponse
    {
        $ok = $this->queueMonitorService->deleteFailedJob($uuid);

        return response()->json(
            $ok ? ['success' => true, 'message' => 'Đã xoá job khỏi danh sách fail.']
                : ['success' => false, 'message' => 'Không tìm thấy job.'],
            $ok ? 200 : 404
        );
    }

    public function retryAll(): JsonResponse
    {
        $count = $this->queueMonitorService->retryAllFailedJobs();

        return response()->json([
            'success' => true,
            'message' => "Đã đưa {$count} job vào lại queue.",
        ]);
    }
}
