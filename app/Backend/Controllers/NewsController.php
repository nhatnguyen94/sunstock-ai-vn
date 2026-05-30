<?php

namespace App\Backend\Controllers;

use App\Backend\Interfaces\NewsServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NewsController extends Controller
{
    public function __construct(
        protected NewsServiceInterface $newsService
    ) {}

    /**
     * Display paginated news list with optional filters.
     * Gate: manage-features (Admin + AdminSupport).
     */
    public function index(Request $request): View
    {
        $filters    = $request->only('search', 'source', 'category_id', 'date_from', 'date_to');
        $news       = $this->newsService->listNews($filters, 30);
        $sources    = $this->newsService->getSources();
        $categories = $this->newsService->getCategories();

        return view('backend.news.index', compact('news', 'sources', 'categories', 'filters'));
    }

    /**
     * Trigger RSS sync from all configured sources.
     * Gate: manage-features (Admin + AdminSupport).
     */
    public function updateRss(): RedirectResponse
    {
        $result = $this->newsService->syncFromAllSources();

        $msg = "Đã đồng bộ {$result['synced']} bài viết mới.";
        if (!empty($result['errors'])) {
            $msg .= ' Lỗi: ' . implode('; ', $result['errors']);
            return redirect()->route('admin.news.index')->with('warning', $msg);
        }

        return redirect()->route('admin.news.index')->with('success', $msg);
    }
}