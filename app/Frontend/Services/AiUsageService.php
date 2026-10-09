<?php

namespace App\Frontend\Services;

use App\Models\AiRequest;
use App\Models\User;
use App\Support\SiteSettings;
use App\Support\TransformerResponse;
use Throwable;

/**
 * The admin's hand on the AI endpoints: a kill switch, a per-account block, an optional daily quota — and a row in `ai_requests`
 * for every call (including the refused ones), so the admin can see who uses it, which model answered and how often it fails.
 * The per-minute limiters in AppServiceProvider stay as they are; these rules come on top and are edited in Admin > Quản lý AI.
 */
class AiUsageService
{
    /**
     * Why this account may not call the AI right now, or null when it may.
     *
     * @return array{message: string, status: int}|null
     */
    public function refusal(User $user): ?array
    {
        $settings = SiteSettings::ai();

        if (! $settings['enabled']) {
            return ['message' => 'Tính năng AI đang được quản trị viên tạm tắt. Vui lòng quay lại sau.', 'status' => TransformerResponse::HTTP_SERVICE_UNAVAILABLE];
        }
        if ($user->ai_blocked_at !== null) {
            return ['message' => 'Tài khoản của bạn đang bị tạm khóa tính năng AI. Vui lòng liên hệ quản trị viên.', 'status' => TransformerResponse::HTTP_FORBIDDEN];
        }
        if ($settings['daily_limit'] > 0 && $this->usedToday($user) >= $settings['daily_limit']) {
            return ['message' => "Bạn đã dùng hết {$settings['daily_limit']} lượt AI trong hôm nay. Lượt mới sẽ có vào ngày mai.", 'status' => TransformerResponse::HTTP_TOO_MANY_REQUESTS];
        }

        return null;
    }

    /** Calls this account made today (Vietnam time) that actually reached the provider; refused calls do not use the quota up. */
    public function usedToday(User $user): int
    {
        return AiRequest::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', AiRequest::STATUS_REFUSED)
            ->where('created_at', '>=', now('Asia/Ho_Chi_Minh')->startOfDay()->utc())
            ->count();
    }

    public function record(?User $user, string $kind, string $status, ?string $model, float $startedAt, ?string $question = null): void
    {
        try {
            AiRequest::create([
                'user_id' => $user?->id,
                'kind' => $kind,
                'status' => $status,
                'model' => $model !== null ? mb_substr($model, 0, 80) : null,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'question' => $question !== null ? mb_substr($question, 0, 300) : null,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // bookkeeping must never break an answer
        }
    }
}
