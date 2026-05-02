<?php

namespace App\Backend\Controllers;

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PortfolioController extends Controller
{
    /**
     * Hiển thị danh sách portfolios
     * Admin và AdminSupport có quyền
     */
    public function index(Request $request)
    {
        // Kiểm tra quyền
        if (!Gate::allows('manage-features')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        $portfolios = Portfolio::with('user', 'items')
            ->when($request->search, function($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhereHas('user', function($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            })
            ->when($request->status, function($query, $status) {
                $query->where('is_active', $status === 'active');
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('backend.portfolios.index', compact('portfolios'));
    }

    /**
     * Hiển thị chi tiết portfolio
     */
    public function show(Portfolio $portfolio)
    {
        if (!Gate::allows('manage-features')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        $portfolio->load('user', 'items.stock');
        return view('backend.portfolios.show', compact('portfolio'));
    }

    /**
     * Kích hoạt/vô hiệu hóa portfolio
     */
    public function toggleStatus(Portfolio $portfolio)
    {
        if (!Gate::allows('manage-features')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        $portfolio->update([
            'is_active' => !$portfolio->is_active
        ]);

        $status = $portfolio->is_active ? 'kích hoạt' : 'vô hiệu hóa';
        
        return redirect()->route('admin.portfolios.index')
            ->with('success', "Portfolio đã được {$status} thành công!");
    }

    /**
     * Xóa portfolio
     */
    public function destroy(Portfolio $portfolio)
    {
        if (!Gate::allows('manage-features')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        $portfolio->delete();

        return redirect()->route('admin.portfolios.index')
            ->with('success', 'Portfolio đã được xóa thành công!');
    }

    /**
     * Thống kê portfolios
     */
    public function stats()
    {
        if (!Gate::allows('manage-features')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        $stats = [
            'total' => Portfolio::count(),
            'active' => Portfolio::where('is_active', true)->count(),
            'with_items' => Portfolio::has('items')->count(),
            'avg_items_per_portfolio' => Portfolio::withCount('items')->avg('items_count'),
        ];

        return view('backend.portfolios.stats', compact('stats'));
    }
}