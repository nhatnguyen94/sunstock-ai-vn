<?php

namespace App\Services;

interface NewsServiceInterface
{
    /**
     * Lấy tin tức mới nhất từ CafeF RSS
     * @param int $limit
     * @return array
     */
    public function getLatestNews($limit = 8);
}