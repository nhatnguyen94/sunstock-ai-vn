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

        $portfolios = Portfolio::with('user')
            ->withCount('items')
            // The search OR must be grouped: unparenthesised, `name LIKE ? OR user LIKE ?` swallowed the status filter
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($request->status, function ($query, $status) {
                $query->where('is_active', $status === 'active');
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Real numbers for the header cards (they used to be hard-coded placeholders: "75%", "+12%", "+8.5%")
        $totals = Portfolio::selectRaw('COUNT(*) as total, SUM(is_active) as active, COALESCE(SUM(current_value),0) as value, COALESCE(SUM(total_invested),0) as invested, COUNT(DISTINCT user_id) as owners')->first();
        $summary = [
            'total' => (int) $totals->total,
            'active' => (int) $totals->active,
            'value' => (float) $totals->value,
            'invested' => (float) $totals->invested,
            'owners' => (int) $totals->owners,
            'profit_percent' => $totals->invested > 0 ? (($totals->value - $totals->invested) / $totals->invested) * 100 : 0,
        ];

        return view('backend.portfolios.index', compact('portfolios', 'summary'));
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

        $value = (float) Portfolio::sum('current_value');
        $invested = (float) Portfolio::sum('total_invested');

        $stats = [
            'total' => Portfolio::count(),
            'active' => Portfolio::where('is_active', true)->count(),
            'with_items' => Portfolio::has('items')->count(),
            'avg_items_per_portfolio' => (float) Portfolio::withCount('items')->avg('items_count'),
            'owners' => Portfolio::distinct('user_id')->count('user_id'),
            'value' => $value,
            'invested' => $invested,
            'profit_percent' => $invested > 0 ? (($value - $invested) / $invested) * 100 : 0,
            // What users actually hold: the ten most common symbols across all portfolios
            'top_symbols' => \App\Models\PortfolioItem::selectRaw('stock_symbol, COUNT(DISTINCT portfolio_id) as portfolios, SUM(quantity * current_price) as value')
                ->groupBy('stock_symbol')
                ->orderByDesc('portfolios')
                ->orderByDesc('value')
                ->limit(10)
                ->get(),
        ];

        return view('backend.portfolios.stats', compact('stats'));
    }
}