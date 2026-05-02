<?php

namespace App\Backend\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TimelineController extends Controller
{
    /**
     * Hiển thị timeline hệ thống
     * Admin, Webadmin, AdminSupport đều có quyền truy cập
     */
    public function index(Request $request)
    {
        // Kiểm tra quyền
        if (!Gate::allows('view-timeline')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        // Giả lập data timeline - trong thực tế sẽ lấy từ activity log
        $timeline_items = collect([
            [
                'id' => 1,
                'type' => 'user_register',
                'user' => 'Nguyen Van A',
                'action' => 'đăng ký tài khoản mới',
                'description' => 'Đăng ký với email: nguyenvana@example.com',
                'created_at' => now()->subMinutes(15),
                'icon' => 'user-plus',
                'color' => 'green'
            ],
            [
                'id' => 2,
                'type' => 'portfolio_created',
                'user' => 'Tran Thi B',
                'action' => 'tạo portfolio mới',
                'description' => 'Portfolio "Đầu tư dài hạn" đã được tạo',
                'created_at' => now()->subMinutes(30),
                'icon' => 'briefcase',
                'color' => 'blue'
            ],
            [
                'id' => 3,
                'type' => 'stock_added',
                'user' => 'Le Van C',
                'action' => 'thêm cổ phiếu vào portfolio',
                'description' => 'Thêm VCB x100 vào portfolio "Tech Stocks"',
                'created_at' => now()->subHours(1),
                'icon' => 'trending-up',
                'color' => 'yellow'
            ],
            [
                'id' => 4,
                'type' => 'admin_action',
                'user' => 'Admin',
                'action' => 'cập nhật dữ liệu stock',
                'description' => 'Cập nhật giá cổ phiếu từ VnDirect',
                'created_at' => now()->subHours(2),
                'icon' => 'refresh',
                'color' => 'purple'
            ],
            [
                'id' => 5,
                'type' => 'system_backup',
                'user' => 'System',
                'action' => 'sao lưu dữ liệu',
                'description' => 'Backup database thành công',
                'created_at' => now()->subHours(4),
                'icon' => 'database',
                'color' => 'gray'
            ],
        ]);

        // Filter theo type nếu có
        if ($request->type) {
            $timeline_items = $timeline_items->where('type', $request->type);
        }

        // Filter theo ngày nếu có
        if ($request->date) {
            $date = \Carbon\Carbon::parse($request->date);
            $timeline_items = $timeline_items->filter(function($item) use ($date) {
                return $item['created_at']->isSameDay($date);
            });
        }

        // Pagination thủ công
        $page = $request->get('page', 1);
        $perPage = 10;
        $offset = ($page - 1) * $perPage;
        $items = $timeline_items->slice($offset, $perPage);

        return view('backend.timeline.index', compact('items', 'timeline_items'));
    }

    /**
     * Lấy thống kê timeline theo loại hoạt động
     */
    public function stats()
    {
        if (!Gate::allows('view-timeline')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        // Thống kê hoạt động theo ngày
        $stats = [
            'today' => [
                'user_actions' => 15,
                'admin_actions' => 3,
                'system_actions' => 5,
            ],
            'this_week' => [
                'user_actions' => 89,
                'admin_actions' => 12,
                'system_actions' => 35,
            ],
            'this_month' => [
                'user_actions' => 234,
                'admin_actions' => 45,
                'system_actions' => 140,
            ],
        ];

        return view('backend.timeline.stats', compact('stats'));
    }
}