<?php

namespace App\Backend\Repositories;

use App\Backend\Interfaces\ActivityLogRepositoryInterface;
use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ActivityLogRepository implements ActivityLogRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return ActivityLog::query()
            ->when($filters['type'] ?? null, fn($q, $type) => $q->where('event_type', $type))
            ->when($filters['date'] ?? null, fn($q, $date) => $q->whereDate('created_at', $date))
            ->when($filters['search'] ?? null, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('description', 'like', "%{$s}%")
                  ->orWhere('user_name', 'like', "%{$s}%");
            }))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function countByType(): array
    {
        return ActivityLog::query()
            ->selectRaw('event_type, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('event_type')
            ->pluck('total', 'event_type')
            ->toArray();
    }
}
