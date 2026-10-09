<?php

namespace App\Backend\Controllers;

use App\Backend\Services\AdminAlertsService;
use App\Backend\Services\SecurityService;
use App\Models\BlockedIp;
use App\Models\Role;
use App\Support\ActivityLogger;
use App\Support\TransformerResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin > Bảo mật: sign-in history, addresses that look like password guessing, blocked addresses. Reading follows `manage-users`;
 * blocking and unblocking are changes, so the route group also carries `admin.only` (the admin role only).
 */
class SecurityController extends Controller
{
    public function index(Request $request, SecurityService $security): View
    {
        Gate::authorize('manage-users');

        $failedOnly = $request->boolean('failed');

        return view('backend.security.index', [
            'overview' => $security->overview(),
            'suspicious' => $security->suspiciousIps(),
            'recent' => $security->recent($failedOnly),
            'blocked' => $security->blocked(),
            'failedOnly' => $failedOnly,
            'threshold' => SecurityService::SUSPICIOUS_FAILURES,
            'canChange' => $request->user()->hasRole(Role::ADMIN),
            'myIp' => $request->ip(),
        ]);
    }

    public function block(Request $request, SecurityService $security): RedirectResponse
    {
        Gate::authorize('manage-users');

        $data = $request->validate(['ip' => 'required|string|max:45', 'reason' => 'nullable|string|max:200']);

        if ($problem = $security->blockProblem(trim($data['ip']), (string) $request->ip())) {
            return TransformerResponse::backError($problem);
        }

        $row = $security->block(trim($data['ip']), $data['reason'] ?? null, $request->user());
        AdminAlertsService::forget();
        ActivityLogger::log('admin_action', "Chặn IP {$row->ip}", ['ip' => $row->ip, 'reason' => $row->reason]);

        return TransformerResponse::backSuccess("Đã chặn {$row->ip}.");
    }

    public function unblock(BlockedIp $blockedIp, SecurityService $security): RedirectResponse
    {
        Gate::authorize('manage-users');

        $ip = $blockedIp->ip;
        $security->unblock($blockedIp);
        ActivityLogger::log('admin_action', "Bỏ chặn IP {$ip}", ['ip' => $ip]);

        return TransformerResponse::backSuccess("Đã bỏ chặn {$ip}.");
    }
}
