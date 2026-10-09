<?php

namespace App\Backend\Controllers;

use App\Backend\Services\AiMonitorService;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\SiteSettings;
use App\Support\TransformerResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Admin > Quản lý AI: usage, the kill switch, the daily quota and blocking one account from the AI. */
class AiMonitorController extends Controller
{
    public function index(AiMonitorService $monitor): View
    {
        Gate::authorize('manage-features');

        return view('backend.ai.index', [
            'overview' => $monitor->overview(),
            'byModel' => $monitor->byModel(),
            'topUsers' => $monitor->topUsers(),
            'recent' => $monitor->recent(),
            'settings' => SiteSettings::ai(),
            'canSeePeople' => Gate::allows('manage-users'),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        Gate::authorize('manage-features');

        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            'daily_limit' => 'required|integer|min:0|max:1000',
        ]);

        $before = SiteSettings::ai();
        SiteSettings::set('ai', ['enabled' => $request->boolean('enabled'), 'daily_limit' => (int) $data['daily_limit']]);

        ActivityLogger::log('admin_action', 'Đổi cài đặt AI', ['before' => $before, 'after' => SiteSettings::ai()]);

        return TransformerResponse::backSuccess('Đã lưu cài đặt AI.');
    }

    public function toggleBlock(User $user): RedirectResponse
    {
        Gate::authorize('manage-features');

        $blocked = $user->ai_blocked_at === null;
        $user->forceFill(['ai_blocked_at' => $blocked ? now() : null])->save();

        ActivityLogger::log('admin_action', ($blocked ? 'Khóa' : 'Mở khóa').' AI của user #'.$user->id, ['user_id' => $user->id, 'blocked' => $blocked]);

        return TransformerResponse::backSuccess($blocked ? 'Đã khóa tính năng AI của tài khoản này.' : 'Đã mở khóa AI cho tài khoản này.');
    }
}
