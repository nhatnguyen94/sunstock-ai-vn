<?php

namespace App\Backend\Repositories;

use App\Backend\Interfaces\NewsRepositoryInterface;
use App\Models\News;
use App\Models\NewsCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NewsRepository implements NewsRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return News::query()
            ->with('category')
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where('title', 'like', '%' . $search . '%');
            })
            ->when($filters['source'] ?? null, function ($q, $source) {
                $q->where('source', $source);
            })
            ->when($filters['category_id'] ?? null, function ($q, $catId) {
                $q->where('category_id', $catId);
            })
            ->when($filters['date_from'] ?? null, function ($q, $date) {
                $q->where('published_at', '>=', $date . ' 00:00:00');
            })
            ->when($filters['date_to'] ?? null, function ($q, $date) {
                $q->where('published_at', '<=', $date . ' 23:59:59');
            })
            ->orderByDesc('published_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function insertNew(array $items): int
    {
        if (empty($items)) {
            return 0;
        }

        $hashes   = array_column($items, 'url_hash');
        $existing = News::whereIn('url_hash', $hashes)->pluck('url_hash')->flip()->toArray();
        $newItems = array_values(array_filter($items, fn($item) => !isset($existing[$item['url_hash']])));

        if (empty($newItems)) {
            return 0;
        }

        foreach (array_chunk($newItems, 100) as $chunk) {
            DB::table('news')->insert($chunk);
        }

        return count($newItems);
    }

    public function getCategories(): Collection
    {
        return NewsCategory::orderBy('name')->get();
    }

    public function getSources(): array
    {
        return News::distinct()->orderBy('source')->pluck('source')->toArray();
    }

    public function getLatestSyncTime(): ?Carbon
    {
        $max = News::max('synced_at');
        return $max ? Carbon::parse($max) : null;
    }
}

