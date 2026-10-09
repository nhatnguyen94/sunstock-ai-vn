<?php

namespace App\Backend\Services;

use App\Frontend\Services\AiService;
use App\Models\AiRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Numbers for Admin > Quản lý AI: how much the AI is used, by whom, which model answers, how often it fails. */
class AiMonitorService
{
    public function __construct(private readonly AiService $ai) {}

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $dayStart = now('Asia/Ho_Chi_Minh')->startOfDay()->utc();
        $weekStart = now()->subDays(7);

        $count = fn ($from, ?string $status = null) => AiRequest::query()
            ->where('created_at', '>=', $from)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->count();

        $weekOk = $count($weekStart, AiRequest::STATUS_OK);
        $weekError = $count($weekStart, AiRequest::STATUS_ERROR);

        return [
            'today' => $count($dayStart),
            'week' => $count($weekStart),
            'week_ok' => $weekOk,
            'week_error' => $weekError,
            'week_refused' => $count($weekStart, AiRequest::STATUS_REFUSED),
            'success_rate' => $weekOk + $weekError > 0 ? round($weekOk / ($weekOk + $weekError) * 100, 1) : null,
            'avg_ms' => (int) round((float) AiRequest::query()->where('created_at', '>=', $weekStart)->where('status', AiRequest::STATUS_OK)->avg('duration_ms')),
            'users_7d' => (int) AiRequest::query()->where('created_at', '>=', $weekStart)->whereNotNull('user_id')->distinct()->count('user_id'),
            'blocked_users' => User::query()->whereNotNull('ai_blocked_at')->count(),
            'models' => $this->ai->models(),
            'key_set' => (string) config('services.groq.key') !== '',
        ];
    }

    /** @return Collection<int, object{model: ?string, answers: int, avg_ms: int, last_at: string}> models that answered in the last 7 days */
    public function byModel(): Collection
    {
        return AiRequest::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->where('status', AiRequest::STATUS_OK)
            ->select('model', DB::raw('count(*) as answers'), DB::raw('avg(duration_ms) as avg_ms'), DB::raw('max(created_at) as last_at'))
            ->groupBy('model')
            ->orderByDesc('answers')
            ->get()
            ->map(function ($row) {
                $row->avg_ms = (int) round((float) $row->avg_ms);

                return $row;
            });
    }

    /** @return Collection<int, object> the heaviest users of the last 7 days, with today's count and whether the AI is switched off for them */
    public function topUsers(int $limit = 10): Collection
    {
        $dayStart = now('Asia/Ho_Chi_Minh')->startOfDay()->utc();
        $rows = AiRequest::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('user_id')
            ->select(
                'user_id',
                DB::raw('count(*) as total'),
                DB::raw("sum(case when status = 'refused' then 1 else 0 end) as refused"),
                DB::raw("sum(case when status = 'error' then 1 else 0 end) as errors"),
                DB::raw('sum(case when created_at >= ? then 1 else 0 end) as today'),
            )
            ->addBinding($dayStart->toDateTimeString(), 'select')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $users = User::query()->whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');

        return $rows->map(function ($row) use ($users) {
            $row->user = $users->get($row->user_id);

            return $row;
        })->filter(fn ($row) => $row->user !== null)->values();
    }

    /** @return Collection<int, AiRequest> */
    public function recent(int $limit = 25): Collection
    {
        return AiRequest::query()->with('user:id,name,email')->orderByDesc('id')->limit($limit)->get();
    }
}
