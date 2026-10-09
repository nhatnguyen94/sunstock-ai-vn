<?php

namespace App\Backend\Controllers;

use App\Backend\Interfaces\QueueMonitorServiceInterface;
use App\Support\ActivityLogger;
use App\Support\TransformerResponse;
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
        $activity = $this->queueMonitorService->getLiveActivity();

        return view('backend.queue-monitor.index', compact('queues', 'failedJobs', 'activity'));
    }

    /**
     * AJAX endpoint polled by the page every 5s for live updates: queue
     * depth, currently-processing jobs, and recently-finished jobs.
     */
    public function stats(): JsonResponse
    {
        return TransformerResponse::json([
            'queues' => $this->queueMonitorService->getQueueStats(),
            ...$this->queueMonitorService->getLiveActivity(),
        ]);
    }

    public function retry(string $uuid): JsonResponse
    {
        $ok = $this->queueMonitorService->retryFailedJob($uuid);
        ActivityLogger::log('admin_action', 'Retry job lỗi '.$uuid, ['uuid' => $uuid, 'found' => $ok]);

        return $ok ? TransformerResponse::success('Đã đưa job vào lại queue.') : TransformerResponse::notFound('Không tìm thấy job.');
    }

    public function destroy(string $uuid): JsonResponse
    {
        $ok = $this->queueMonitorService->deleteFailedJob($uuid);
        ActivityLogger::log('admin_action', 'Xoá job lỗi '.$uuid, ['uuid' => $uuid, 'found' => $ok]);

        return $ok ? TransformerResponse::success('Đã xoá job khỏi danh sách fail.') : TransformerResponse::notFound('Không tìm thấy job.');
    }

    public function destroyAll(): JsonResponse
    {
        $count = $this->queueMonitorService->deleteAllFailedJobs();

        ActivityLogger::log('admin_action', "Xoá toàn bộ job thất bại: {$count} job");

        return TransformerResponse::success("Đã xoá {$count} job thất bại.");
    }

    public function retryAll(): JsonResponse
    {
        $count = $this->queueMonitorService->retryAllFailedJobs();

        ActivityLogger::log('admin_action', "Retry toàn bộ job lỗi: {$count} job");

        return TransformerResponse::success("Đã đưa {$count} job vào lại queue.");
    }
}
