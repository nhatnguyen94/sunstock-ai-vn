<?php

namespace App\Backend\Services;

use App\Backend\Interfaces\NewsCategoryRepositoryInterface;
use App\Backend\Interfaces\NewsCategoryServiceInterface;
use App\Backend\Interfaces\NewsServiceInterface;
use App\Models\NewsCategory;
use Illuminate\Support\Collection;

class NewsCategoryService implements NewsCategoryServiceInterface
{
    public function __construct(
        protected NewsCategoryRepositoryInterface $newsCategoryRepository,
        protected NewsServiceInterface $newsService
    ) {}

    public function listCategories(): Collection
    {
        return $this->newsCategoryRepository->all();
    }

    public function createCategory(array $data): NewsCategory
    {
        return $this->newsCategoryRepository->create($data);
    }

    public function updateCategory(NewsCategory $category, array $data): void
    {
        $this->newsCategoryRepository->update($category, $data);
    }

    public function isInUse(NewsCategory $category): bool
    {
        if ($category->news()->exists()) {
            return true;
        }

        return in_array($category->id, $this->newsService->usedCategoryIds(), true);
    }

    public function deleteCategory(NewsCategory $category): void
    {
        $this->newsCategoryRepository->delete($category);
    }
}
