<?php

namespace App\Backend\Repositories;

use App\Backend\Interfaces\NewsCategoryRepositoryInterface;
use App\Models\NewsCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NewsCategoryRepository implements NewsCategoryRepositoryInterface
{
    public function all(): Collection
    {
        return NewsCategory::withCount('news')->orderBy('name')->get();
    }

    public function create(array $data): NewsCategory
    {
        return NewsCategory::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
        ]);
    }

    public function update(NewsCategory $category, array $data): void
    {
        $category->update([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
        ]);
    }

    public function delete(NewsCategory $category): void
    {
        $category->delete();
    }
}
