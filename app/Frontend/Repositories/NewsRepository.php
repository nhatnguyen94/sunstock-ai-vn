<?php

namespace App\Frontend\Repositories;

use App\Frontend\Interfaces\NewsRepositoryInterface;
use App\Models\News;
use App\Models\NewsCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class NewsRepository implements NewsRepositoryInterface
{
    public function getLatest(int $limit = 8): Collection
    {
        return News::with('category')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return News::query()
            ->with('category')
            ->when($filters['category'] ?? null, function ($q, $slug) {
                $q->whereHas('category', fn($c) => $c->where('slug', $slug));
            })
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where('title', 'like', '%' . $search . '%');
            })
            ->orderByDesc('published_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getCategories(): Collection
    {
        return NewsCategory::orderBy('name')->get();
    }
}
