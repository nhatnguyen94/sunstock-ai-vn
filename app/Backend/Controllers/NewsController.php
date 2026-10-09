<?php

namespace App\Backend\Controllers;

use App\Backend\Interfaces\NewsServiceInterface;
use App\Models\News;
use App\Support\ActivityLogger;
use App\Support\TransformerResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        $filters = $request->only('search', 'source', 'category_id', 'date_from', 'date_to');
        $news = $this->newsService->listNews($filters, 30);
        $sources = $this->newsService->getSources();
        $categories = $this->newsService->getCategories();

        return view('backend.news.index', compact('news', 'sources', 'categories', 'filters'));
    }

    /** Pin an article to the top of the home page (or unpin it). */
    public function togglePin(News $news): RedirectResponse
    {
        $news->forceFill(['pinned_at' => $news->pinned_at ? null : now()])->save();
        Cache::forget('homepage_news');
        ActivityLogger::log('admin_action', ($news->pinned_at ? 'Ghim' : 'Bỏ ghim')." tin #{$news->id}", ['news_id' => $news->id]);

        return TransformerResponse::backSuccess($news->pinned_at ? 'Đã ghim lên đầu trang chủ.' : 'Đã bỏ ghim.');
    }

    /** Hide an article from every public page (or show it again). The sync never brings a hidden article back as new: it is the same row. */
    public function toggleHide(News $news): RedirectResponse
    {
        $news->forceFill(['is_hidden' => ! $news->is_hidden, 'pinned_at' => null])->save();
        Cache::forget('homepage_news');
        ActivityLogger::log('admin_action', ($news->is_hidden ? 'Ẩn' : 'Hiện lại')." tin #{$news->id}", ['news_id' => $news->id]);

        return TransformerResponse::backSuccess($news->is_hidden ? 'Đã ẩn bài khỏi website.' : 'Đã hiện lại bài.');
    }

    /**
     * Trigger RSS sync from all configured sources.
     * Gate: manage-features (Admin + AdminSupport).
     */
    public function updateRss(): RedirectResponse
    {
        $result = $this->newsService->syncFromAllSources();

        $msg = "Đã đồng bộ {$result['synced']} bài viết mới.";
        ActivityLogger::log('news_sync', "Admin sync RSS: {$result['synced']} bài mới");
        if (! empty($result['errors'])) {
            $msg .= ' Lỗi: '.implode('; ', $result['errors']);

            return TransformerResponse::redirectWarning('admin.news.index', $msg);
        }

        return TransformerResponse::redirectSuccess('admin.news.index', $msg);
    }
}
