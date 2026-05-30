<?php

namespace App\Frontend\Services;

use App\Frontend\Interfaces\NewsRepositoryInterface;
use App\Frontend\Interfaces\NewsServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class NewsService implements NewsServiceInterface
{
    public function __construct(
        protected NewsRepositoryInterface $newsRepository
    ) {}

    public function getLatestNews(int $limit = 8): Collection
    {
        return $this->newsRepository->getLatest($limit);
    }

    public function getPaginatedNews(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->newsRepository->paginate($filters, $perPage);
    }

    public function getCategories(): Collection
    {
        return $this->newsRepository->getCategories();
    }
}

