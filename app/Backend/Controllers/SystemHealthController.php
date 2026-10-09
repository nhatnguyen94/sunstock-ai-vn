<?php

namespace App\Backend\Controllers;

use App\Backend\Services\SystemHealthService;
use App\Models\Role;
use App\Support\ActivityLogger;
use App\Support\TransformerResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Admin > Sức khỏe hệ thống: scheduler heartbeat, disk, what runs when, database backups. Seeing it needs `manage-features`;
 * taking or downloading a backup (a dump holds every account and password hash) needs the `admin` role itself.
 */
class SystemHealthController extends Controller
{
    public function index(Request $request, SystemHealthService $health): View
    {
        Gate::authorize('manage-features');

        return view('backend.health.index', [
            'scheduler' => $health->scheduler(),
            'disk' => $health->disk(),
            'schedule' => $health->schedule(),
            'backups' => $health->backups(),
            'backupAge' => $health->backupAgeDays(),
            'env' => $health->environment(),
            'isAdmin' => $request->user()->hasRole(Role::ADMIN),
        ]);
    }

    /** Queue a backup (it can take minutes: it must not run inside this request). */
    public function backup(Request $request): RedirectResponse
    {
        Gate::authorize('manage-features');
        TransformerResponse::abortUnless($request->user()->hasRole(Role::ADMIN), TransformerResponse::HTTP_FORBIDDEN);

        Artisan::queue('db:backup', ['--force' => true]);
        ActivityLogger::log('admin_action', 'Yêu cầu tạo bản sao lưu database');

        return TransformerResponse::backSuccess('Đã xếp lịch tạo bản sao lưu — vài phút nữa tải lại trang để thấy nó.');
    }

    public function download(Request $request, string $id, SystemHealthService $health): BinaryFileResponse
    {
        Gate::authorize('manage-features');
        TransformerResponse::abortUnless($request->user()->hasRole(Role::ADMIN), TransformerResponse::HTTP_FORBIDDEN);

        $path = $health->backupPath($id);
        TransformerResponse::abortIf($path === null || ! is_file($path), TransformerResponse::HTTP_NOT_FOUND);

        ActivityLogger::log('admin_action', 'Tải bản sao lưu database', ['file' => basename($path)]);

        return response()->download($path);
    }
}
