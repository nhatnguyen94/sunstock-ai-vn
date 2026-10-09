<?php

namespace App\Backend\Services;

use App\Models\ActivityLog;
use App\Models\AiRequest;
use App\Models\LoginAttempt;
use App\Models\PortfolioItem;
use App\Models\User;
use App\Models\WatchlistItem;

/** What Admin > Users > chi tiết shows about one account besides its profile: last sign-ins, watchlist, holdings, AI use, recent activity. */
class UserInsightsService
{
    /** @return array<string, mixed> */
    public function forUser(User $user): array
    {
        $dayStart = now('Asia/Ho_Chi_Minh')->startOfDay()->utc();
        $ai = AiRequest::query()->where('user_id', $user->id);

        return [
            'last_login' => LoginAttempt::query()->where('user_id', $user->id)->where('success', true)->latest('id')->first(),
            'failed_7d' => LoginAttempt::query()->where('email', $user->email)->where('success', false)->where('created_at', '>=', now()->subDays(7))->count(),
            'watchlist' => WatchlistItem::query()->where('user_id', $user->id)->orderBy('symbol')->limit(40)->pluck('symbol')->all(),
            'holdings' => PortfolioItem::query()->whereIn('portfolio_id', $user->portfolios()->select('id'))->distinct()->orderBy('stock_symbol')->pluck('stock_symbol')->all(),
            'ai' => [
                'today' => (clone $ai)->where('created_at', '>=', $dayStart)->where('status', '!=', AiRequest::STATUS_REFUSED)->count(),
                'week' => (clone $ai)->where('created_at', '>=', now()->subDays(7))->count(),
                'errors' => (clone $ai)->where('created_at', '>=', now()->subDays(7))->where('status', AiRequest::STATUS_ERROR)->count(),
                'blocked' => $user->ai_blocked_at !== null,
                'recent' => (clone $ai)->whereNotNull('question')->latest('id')->limit(5)->get(['question', 'status', 'created_at']),
            ],
            'activity' => ActivityLog::query()->where('user_id', $user->id)->orderByDesc('created_at')->limit(12)->get(),
        ];
    }
}
