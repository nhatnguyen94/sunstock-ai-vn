<?php

namespace App\Backend\Interfaces;

use App\Models\NewsCategory;
use Illuminate\Support\Collection;

interface NewsCategoryServiceInterface
{
    /** Each entry decorated with a `news_count` attribute for the index table. */
    public function listCategories(): Collection;

    public function createCategory(array $data): NewsCategory;

    public function updateCategory(NewsCategory $category, array $data): void;

    /**
     * A category is "in use" if it has news rows, or is referenced by
     * NewsService::SOURCES's hardcoded category_id (the RSS sync would
     * insert future articles under an id that no longer resolves to anything).
     */
    public function isInUse(NewsCategory $category): bool;

    public function deleteCategory(NewsCategory $category): void;
}
