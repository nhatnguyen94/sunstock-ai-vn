<?php

namespace App\Frontend\Controllers;

use App\Frontend\Services\PortfolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public function __construct(
        private PortfolioService $portfolioService
    ) {}

    /**
     * Display portfolio dashboard
     */
    public function index(): View
    {
        $user = Auth::user();
        $portfolios = $this->portfolioService->getUserPortfolios($user->id, true);

        // Calculate total stats across all portfolios
        $totalStats = [
            'total_portfolios' => $portfolios->count(),
            'total_invested' => $portfolios->sum('total_invested'),
            'current_value' => $portfolios->sum('current_value'),
        ];

        $totalStats['profit_loss'] = $totalStats['current_value'] - $totalStats['total_invested'];
        $totalStats['profit_loss_percent'] = $totalStats['total_invested'] > 0
            ? ($totalStats['profit_loss'] / $totalStats['total_invested']) * 100
            : 0;
        $totalStats['is_positive'] = $totalStats['profit_loss'] >= 0;

        return view('portfolio.index', compact('portfolios', 'totalStats'));
    }

    /**
     * Show specific portfolio details
     */
    public function show(int $id): View
    {
        $user = Auth::user();
        $analytics = $this->portfolioService->getPortfolioAnalytics($id, $user->id);

        if (! $analytics) {
            abort(404, 'Portfolio không tồn tại hoặc bạn không có quyền truy cập.');
        }

        return view('portfolio.show', $analytics);
    }

    /**
     * Show create portfolio form
     */
    public function create(): View
    {
        return view('portfolio.create');
    }

    /**
     * Store new portfolio
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ], [
            'name.required' => 'Tên danh mục đầu tư là bắt buộc.',
            'name.max' => 'Tên danh mục không được quá 255 ký tự.',
            'description.max' => 'Mô tả không được quá 1000 ký tự.',
        ]);

        try {
            $portfolio = $this->portfolioService->createPortfolio(Auth::id(), $validated);

            return redirect()
                ->route('portfolio.show', $portfolio->id)
                ->with('success', 'Tạo danh mục đầu tư thành công!');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Có lỗi xảy ra khi tạo danh mục đầu tư.'])
                ->withInput();
        }
    }

    /**
     * Show edit portfolio form
     */
    public function edit(int $id): View
    {
        $user = Auth::user();
        $portfolio = $this->portfolioService->getPortfolioById($id, $user->id);

        if (! $portfolio) {
            abort(404, 'Portfolio không tồn tại hoặc bạn không có quyền truy cập.');
        }

        return view('portfolio.edit', compact('portfolio'));
    }

    /**
     * Update portfolio
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ], [
            'name.required' => 'Tên danh mục đầu tư là bắt buộc.',
            'name.max' => 'Tên danh mục không được quá 255 ký tự.',
            'description.max' => 'Mô tả không được quá 1000 ký tự.',
        ]);

        try {
            $portfolio = $this->portfolioService->updatePortfolio($id, Auth::id(), $validated);

            if (! $portfolio) {
                return back()->withErrors(['error' => 'Portfolio không tồn tại hoặc bạn không có quyền chỉnh sửa.']);
            }

            return redirect()
                ->route('portfolio.show', $id)
                ->with('success', 'Cập nhật danh mục đầu tư thành công!');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Có lỗi xảy ra khi cập nhật danh mục đầu tư.'])
                ->withInput();
        }
    }

    /**
     * Delete portfolio
     */
    public function destroy(int $id): RedirectResponse
    {
        try {
            $deleted = $this->portfolioService->deletePortfolio($id, Auth::id());

            if (! $deleted) {
                return back()->withErrors(['error' => 'Portfolio không tồn tại hoặc bạn không có quyền xóa.']);
            }

            return redirect()
                ->route('portfolio.index')
                ->with('success', 'Xóa danh mục đầu tư thành công!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Có lỗi xảy ra khi xóa danh mục đầu tư.']);
        }
    }

    /**
     * Show add stock to portfolio form
     */
    public function addStock(int $id): View
    {
        $user = Auth::user();
        $portfolio = $this->portfolioService->getPortfolioById($id, $user->id);

        if (! $portfolio) {
            abort(404, 'Portfolio không tồn tại hoặc bạn không có quyền truy cập.');
        }

        return view('portfolio.add-stock', compact('portfolio'));
    }

    /**
     * Store stock in portfolio
     */
    public function storeStock(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'stock_symbol' => 'required|string|max:10',
            'stock_name' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'buy_price' => 'required|numeric|min:0.01',
            'buy_date' => 'required|date|before_or_equal:today',
            'target_price' => 'nullable|numeric|min:0.01',
            'stop_loss_price' => 'nullable|numeric|min:0.01',
            'notes' => 'nullable|string|max:1000',
        ], [
            'stock_symbol.required' => 'Mã cổ phiếu là bắt buộc.',
            'stock_name.required' => 'Tên cổ phiếu là bắt buộc.',
            'quantity.required' => 'Số lượng là bắt buộc.',
            'quantity.min' => 'Số lượng phải lớn hơn 0.',
            'buy_price.required' => 'Giá mua là bắt buộc.',
            'buy_price.min' => 'Giá mua phải lớn hơn 0.',
            'buy_date.required' => 'Ngày mua là bắt buộc.',
            'buy_date.before_or_equal' => 'Ngày mua không được vượt quá hôm nay.',
        ]);

        try {
            $item = $this->portfolioService->addStockToPortfolio($id, Auth::id(), $validated);

            if (! $item) {
                return back()->withErrors(['error' => 'Portfolio không tồn tại hoặc bạn không có quyền thêm cổ phiếu.']);
            }

            return redirect()
                ->route('portfolio.show', $id)
                ->with('success', 'Thêm cổ phiếu vào danh mục thành công!');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Có lỗi xảy ra khi thêm cổ phiếu vào danh mục.'])
                ->withInput();
        }
    }

    /**
     * Update portfolio item
     */
    public function updateItem(Request $request, int $itemId): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'buy_price' => 'required|numeric|min:0.01',
            'target_price' => 'nullable|numeric|min:0.01',
            'stop_loss_price' => 'nullable|numeric|min:0.01',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $item = $this->portfolioService->updatePortfolioItem($itemId, Auth::id(), $validated);

            if (! $item) {
                return back()->withErrors(['error' => 'Không tìm thấy cổ phiếu hoặc bạn không có quyền chỉnh sửa.']);
            }

            return redirect()
                ->route('portfolio.show', $item->portfolio_id)
                ->with('success', 'Cập nhật thông tin cổ phiếu thành công!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Có lỗi xảy ra khi cập nhật thông tin cổ phiếu.']);
        }
    }

    /**
     * Remove stock from portfolio
     */
    public function removeStock(int $itemId): RedirectResponse
    {
        try {
            // Get item first to get portfolio ID for redirect
            $item = $this->portfolioService->getPortfolioById($itemId, Auth::id());
            $portfolioId = $item ? $item->portfolio_id : null;

            $deleted = $this->portfolioService->removeStockFromPortfolio($itemId, Auth::id());

            if (! $deleted) {
                return back()->withErrors(['error' => 'Không tìm thấy cổ phiếu hoặc bạn không có quyền xóa.']);
            }

            if ($portfolioId) {
                return redirect()
                    ->route('portfolio.show', $portfolioId)
                    ->with('success', 'Xóa cổ phiếu khỏi danh mục thành công!');
            }

            return redirect()
                ->route('portfolio.index')
                ->with('success', 'Xóa cổ phiếu khỏi danh mục thành công!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Có lỗi xảy ra khi xóa cổ phiếu khỏi danh mục.']);
        }
    }

    /**
     * Update portfolio prices (AJAX)
     */
    public function updatePrices(int $id): JsonResponse
    {
        try {
            $success = $this->portfolioService->updatePortfolioPrices($id, Auth::id());

            if (! $success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể cập nhật giá cổ phiếu. Portfolio không tồn tại hoặc bạn không có quyền truy cập.',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật giá cổ phiếu thành công!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật giá cổ phiếu.',
            ], 500);
        }
    }

    /**
     * Get rebalance suggestions (AJAX)
     */
    public function getRebalanceSuggestions(int $id): JsonResponse
    {
        try {
            $suggestions = $this->portfolioService->getRebalanceSuggestions($id, Auth::id());

            if ($suggestions === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Portfolio không tồn tại hoặc bạn không có quyền truy cập.',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'suggestions' => $suggestions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo gợi ý rebalance.',
            ], 500);
        }
    }
}
