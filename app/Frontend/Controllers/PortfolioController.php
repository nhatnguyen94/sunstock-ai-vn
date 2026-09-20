<?php

namespace App\Frontend\Controllers;

use App\Frontend\Services\PortfolioService;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        // Numbers on the dashboard are always today's, without pressing anything
        $this->portfolioService->updateAllUserPortfolioPrices($user->id);
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
    public function create(Request $request): View
    {
        return view('portfolio.create', ['symbol' => strtoupper((string) $request->query('symbol'))]);
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

            ActivityLogger::log('portfolio_created', "Tạo portfolio: {$portfolio->name}", ['portfolio_id' => $portfolio->id]);

            // Came here from "add SYMBOL to portfolio": continue straight to the add form
            if ($request->filled('symbol')) {
                return redirect()->route('portfolio.add-stock', ['id' => $portfolio->id, 'symbol' => strtoupper($request->input('symbol'))])
                    ->with('success', 'Đã tạo danh mục. Nhập thông tin mua để hoàn tất.');
            }

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
            // (the edit form posts a hidden 0 before the checkbox so "unchecked" is actually sent)
            $validated['is_active'] = $request->boolean('is_active');
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
    public function addStock(Request $request, int $id): View
    {
        $user = Auth::user();
        $portfolio = $this->portfolioService->getPortfolioById($id, $user->id);

        if (! $portfolio) {
            abort(404, 'Portfolio không tồn tại hoặc bạn không có quyền truy cập.');
        }

        $symbol = strtoupper((string) $request->query('symbol'));

        return view('portfolio.add-stock', compact('portfolio', 'symbol'));
    }

    /**
     * Store stock in portfolio
     */
    public function storeStock(Request $request, int $id): RedirectResponse
    {
        $request->merge(['stock_symbol' => strtoupper(trim((string) $request->input('stock_symbol')))]);

        $validated = $request->validate([
            'stock_symbol' => 'required|string|max:10|exists:stock_symbols,symbol',
            'stock_name' => 'nullable|string|max:255',
            'quantity' => 'required|integer|min:1|max:1000000000',
            // whole VND (85000, not 85): the smallest real VN share price is well above 100
            'buy_price' => 'required|numeric|min:100|max:100000000',
            'buy_date' => 'required|date|before_or_equal:today',
            'target_price' => 'nullable|numeric|min:100|max:100000000',
            'stop_loss_price' => 'nullable|numeric|min:100|max:100000000',
            'notes' => 'nullable|string|max:1000',
        ], [
            'stock_symbol.required' => 'Mã cổ phiếu là bắt buộc.',
            'stock_symbol.exists' => 'Không tìm thấy mã cổ phiếu này. Hãy chọn mã từ danh sách gợi ý.',
            'quantity.required' => 'Số lượng là bắt buộc.',
            'quantity.min' => 'Số lượng phải lớn hơn 0.',
            'buy_price.required' => 'Giá mua là bắt buộc.',
            'buy_price.min' => 'Giá mua tính theo VNĐ, tối thiểu 100 (ví dụ 85000 chứ không phải 85).',
            'target_price.min' => 'Giá mục tiêu tính theo VNĐ (ví dụ 95000).',
            'stop_loss_price.min' => 'Giá cắt lỗ tính theo VNĐ (ví dụ 78000).',
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
            'quantity' => 'required|integer|min:1|max:1000000000',
            'buy_price' => 'required|numeric|min:100|max:100000000',
            'target_price' => 'nullable|numeric|min:100|max:100000000',
            'stop_loss_price' => 'nullable|numeric|min:100|max:100000000',
            'notes' => 'nullable|string|max:1000',
        ], [
            'buy_price.min' => 'Giá mua tính theo VNĐ, tối thiểu 100 (ví dụ 85000).',
            'target_price.min' => 'Giá mục tiêu tính theo VNĐ (ví dụ 95000).',
            'stop_loss_price.min' => 'Giá cắt lỗ tính theo VNĐ (ví dụ 78000).',
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
            // Look the holding up (as its owner) BEFORE deleting it, to know which portfolio to go back to.
            // This used to call getPortfolioById() with the item id, i.e. looked up the wrong table row.
            $item = $this->portfolioService->findItemForUser($itemId, Auth::id());
            $portfolioId = $item?->portfolio_id;

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
     * Refresh prices (AJAX). Says what really happened — how many holdings got a fresh price and which
     * ones have no price data yet (those are queued for a background fetch).
     */
    public function updatePrices(int $id): JsonResponse
    {
        try {
            $result = $this->portfolioService->refreshPrices($id, Auth::id(), queueMissing: true);

            if ($result === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Danh mục không tồn tại hoặc bạn không có quyền truy cập.',
                ], 404);
            }

            if (! $result['ok']) {
                return response()->json(['success' => false, 'message' => 'Không cập nhật được giá, vui lòng thử lại.'], 500);
            }

            $message = $result['updated'] > 0
                ? "Đã cập nhật giá {$result['updated']} mã" . ($result['as_of'] ? ' (dữ liệu phiên ' . date('d/m/Y', strtotime($result['as_of'])) . ')' : '') . '.'
                : 'Chưa có dữ liệu giá cho các mã trong danh mục.';

            if ($result['missing']) {
                $message .= ' Chưa có giá cho: ' . implode(', ', $result['missing'])
                    . ($result['queued'] ? ' — đang tải dữ liệu, thử lại sau ít phút.' : ' (đang được tải).');
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'updated' => $result['updated'],
                'missing' => $result['missing'],
                'as_of' => $result['as_of'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật giá cổ phiếu.',
            ], 500);
        }
    }

    /** Quote for the add-stock form: company name + latest price (VND), so the user does not type them. */
    public function quote(string $symbol): JsonResponse
    {
        $quote = $this->portfolioService->getQuote($symbol);

        return $quote
            ? response()->json(['success' => true] + $quote)
            : response()->json(['success' => false, 'message' => 'Không tìm thấy mã cổ phiếu.'], 404);
    }

    /**
     * "Add to portfolio" entry point from other pages (stock page, company page):
     * no portfolio yet -> create one first; exactly one -> straight to its add form; several -> pick.
     */
    public function quickAdd(Request $request)
    {
        $symbol = strtoupper(trim((string) $request->query('symbol')));
        $symbol = preg_match('/^[A-Z0-9]{2,10}$/', $symbol) ? $symbol : '';

        $portfolios = $this->portfolioService->getUserPortfolios(Auth::id(), true);

        if ($portfolios->isEmpty()) {
            return redirect()->route('portfolio.create', ['symbol' => $symbol])
                ->with('info', 'Tạo danh mục đầu tiên để thêm ' . ($symbol ?: 'cổ phiếu') . ' vào theo dõi.');
        }

        if ($portfolios->count() === 1) {
            return redirect()->route('portfolio.add-stock', ['id' => $portfolios->first()->id, 'symbol' => $symbol]);
        }

        return view('portfolio.choose', compact('portfolios', 'symbol'));
    }

    /** CSV of the holdings (UTF-8 with BOM so Excel shows Vietnamese correctly). */
    public function export(int $id): StreamedResponse
    {
        $analytics = $this->portfolioService->getPortfolioAnalytics($id, Auth::id());

        if (! $analytics) {
            abort(404);
        }

        $name = \Illuminate\Support\Str::slug($analytics['portfolio']->name) ?: 'portfolio';

        return response()->streamDownload(function () use ($analytics) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Mã', 'Tên', 'Số lượng', 'Giá mua', 'Giá hiện tại', 'Giá trị', 'Lãi/Lỗ (₫)', 'Lãi/Lỗ (%)', 'Tỷ trọng (%)', 'Mục tiêu', 'Cắt lỗ', 'Ngày mua']);
            foreach ($analytics['holdings'] as $h) {
                fputcsv($out, [
                    $h['symbol'], $h['name'], $h['quantity'], round($h['buy_price']), round($h['current_price']),
                    round($h['value']), round($h['pnl']), round($h['pnl_percent'], 2), round($h['weight'], 1),
                    $h['target_price'] !== null ? round($h['target_price']) : '', $h['stop_loss_price'] !== null ? round($h['stop_loss_price']) : '',
                    $h['buy_date'],
                ]);
            }
            fclose($out);
        }, "portfolio-{$name}-" . now()->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
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
