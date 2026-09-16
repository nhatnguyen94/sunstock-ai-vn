<?php

namespace App\Backend\Interfaces;

use App\Models\NewsCategory;
use Illuminate\Support\Collection;

interface NewsCategoryRepositoryInterface
{
    public function all(): Collection;

    public function create(array $data): NewsCategory;

    public function update(NewsCategory $category, array $data): void;

    public function delete(NewsCategory $category): void;
}
