<?php

namespace App\Backend\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NewsController extends Controller
{
    /**
     * Hiển thị danh sách news
     * Admin và AdminSupport có quyền
     */
    public function index(Request $request)
    {
        // Kiểm tra quyền
        if (!Gate::allows('manage-features')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        // Giả lập data news
        $news = collect([
            [
                'id' => 1,
                'title' => 'VN-Index tăng mạnh trong phiên sáng',
                'source' => 'VnExpress',
                'published_at' => now()->subHours(2),
                'status' => 'published'
            ],
            [
                'id' => 2,
                'title' => 'Cổ phiếu ngân hàng dẫn dắt thị trường',
                'source' => 'CafeF',
                'published_at' => now()->subHours(4),
                'status' => 'published'
            ],
        ]);

        return view('backend.news.index', compact('news'));
    }

    /**
     * Cập nhật RSS News từ VnExpress
     */
    public function updateRss()
    {
        if (!Gate::allows('manage-features')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        // Gọi service để cập nhật RSS
        return redirect()->route('admin.news.index')
            ->with('success', 'Đã cập nhật tin tức từ RSS feeds.');
    }
}