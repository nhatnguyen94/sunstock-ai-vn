<?php

namespace App\Frontend\Controllers;

use App\Frontend\Interfaces\NewsServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class NewsController extends Controller
{
    public function __construct(
        protected NewsServiceInterface $newsService
    ) {}

    /**
     * Show paginated news list, optionally filtered by category slug.
     */
    public function index(Request $request, ?string $categorySlug = null): View
    {
        $filters = [
            'category' => $categorySlug,
            'search'   => $request->input('search'),
        ];

        $news       = $this->newsService->getPaginatedNews($filters, 15);
        $categories = Cache::remember('frontend_news_categories', 3600, fn() => $this->newsService->getCategories());

        return view('news.index', compact('news', 'categories', 'categorySlug'));
    }
}
