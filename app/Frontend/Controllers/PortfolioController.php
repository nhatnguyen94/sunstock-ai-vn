<?php

namespace App\Frontend\Controllers;

use App\Frontend\Services\PortfolioInsightsService;
use App\Frontend\Services\PortfolioLedgerService;
use App\Frontend\Services\PortfolioService;
use App\Models\PortfolioTransaction;
use App\Support\ActivityLogger;
use App\Support\TransformerResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortfolioController extends Controller
{
    public function __construct(
        private PortfolioService $portfolioService,
        private PortfolioLedgerService $ledger,
        private PortfolioInsightsService $insights
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
            TransformerResponse::abortWith(TransformerResponse::HTTP_NOT_FOUND, TransformerResponse::PORTFOLIO_NOT_FOUND_MESSAGE);
        }

        return view('portfolio.show', $this->insights->enhance($analytics));
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
                return TransformerResponse::redirectSuccess('portfolio.add-stock', 'Đã tạo danh mục. Nhập thông tin mua để hoàn tất.', ['id' => $portfolio->id, 'symbol' => strtoupper($request->input('symbol'))]);
            }

            return TransformerResponse::redirectSuccess('portfolio.show', 'Tạo danh mục đầu tư thành công!', [$portfolio->id]);
        } catch (Exception $e) {
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
            TransformerResponse::abortWith(TransformerResponse::HTTP_NOT_FOUND, TransformerResponse::PORTFOLIO_NOT_FOUND_MESSAGE);
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

            return TransformerResponse::redirectSuccess('portfolio.show', 'Cập nhật danh mục đầu tư thành công!', [$id]);
        } catch (Exception $e) {
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

            return TransformerResponse::redirectSuccess('portfolio.index', 'Xóa danh mục đầu tư thành công!');
        } catch (Exception $e) {
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
            TransformerResponse::abortWith(TransformerResponse::HTTP_NOT_FOUND, TransformerResponse::PORTFOLIO_NOT_FOUND_MESSAGE);
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

            return TransformerResponse::redirectSuccess('portfolio.show', 'Thêm cổ phiếu vào danh mục thành công!', [$id]);
        } catch (Exception $e) {
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

            return TransformerResponse::redirectSuccess('portfolio.show', 'Cập nhật thông tin cổ phiếu thành công!', [$item->portfolio_id]);
        } catch (Exception $e) {
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
                return TransformerResponse::redirectSuccess('portfolio.show', 'Xóa cổ phiếu khỏi danh mục thành công!', [$portfolioId]);
            }

            return TransformerResponse::redirectSuccess('portfolio.index', 'Xóa cổ phiếu khỏi danh mục thành công!');
        } catch (Exception $e) {
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
                return TransformerResponse::notFound('Danh mục không tồn tại hoặc bạn không có quyền truy cập.');
            }

            if (! $result['ok']) {
                return TransformerResponse::serverError('Không cập nhật được giá, vui lòng thử lại.');
            }

            $message = $result['updated'] > 0
                ? "Đã cập nhật giá {$result['updated']} mã".($result['as_of'] ? ' (dữ liệu phiên '.date('d/m/Y', strtotime($result['as_of'])).')' : '').'.'
                : 'Chưa có dữ liệu giá cho các mã trong danh mục.';

            if ($result['missing']) {
                $message .= ' Chưa có giá cho: '.implode(', ', $result['missing'])
                    .($result['queued'] ? ' — đang tải dữ liệu, thử lại sau ít phút.' : ' (đang được tải).');
            }

            return TransformerResponse::success($message, extra: [
                'updated' => $result['updated'],
                'missing' => $result['missing'],
                'as_of' => $result['as_of'],
            ]);
        } catch (Exception $e) {
            return TransformerResponse::serverError('Có lỗi xảy ra khi cập nhật giá cổ phiếu.');
        }
    }

    /** Quote for the add-stock form: company name + latest price (VND), so the user does not type them. */
    public function quote(string $symbol): JsonResponse
    {
        $quote = $this->portfolioService->getQuote($symbol);

        return $quote
            ? TransformerResponse::success(extra: $quote)
            : TransformerResponse::notFound('Không tìm thấy mã cổ phiếu.');
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
            return TransformerResponse::redirectInfo('portfolio.create', 'Tạo danh mục đầu tiên để thêm '.($symbol ?: 'cổ phiếu').' vào theo dõi.', ['symbol' => $symbol]);
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
            TransformerResponse::abortWith(TransformerResponse::HTTP_NOT_FOUND);
        }

        $name = Str::slug($analytics['portfolio']->name) ?: 'portfolio';

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
        }, "portfolio-{$name}-".now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Record a buy or sell (the trade modal on the portfolio page). The holding's quantity / average cost follow
     * the trade; a sell freezes its realised P&L.
     */
    public function storeTransaction(Request $request, int $id): RedirectResponse
    {
        $request->merge(['stock_symbol' => strtoupper(trim((string) $request->input('stock_symbol')))]);

        $validated = $request->validate([
            'type' => 'required|in:buy,sell',
            'stock_symbol' => 'required|string|max:12|exists:stock_symbols,symbol',
            'quantity' => 'required|integer|min:1|max:1000000000',
            // whole VND per share (85000, not 85)
            'price' => 'required|numeric|min:100|max:100000000',
            'fee' => 'nullable|numeric|min:0|max:10000000000',
            'traded_at' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:500',
        ], [
            'type.required' => 'Hãy chọn Mua hoặc Bán.',
            'stock_symbol.exists' => 'Không tìm thấy mã cổ phiếu này. Hãy chọn mã từ danh sách gợi ý.',
            'quantity.min' => 'Số lượng phải lớn hơn 0.',
            'price.min' => 'Giá tính theo VNĐ, tối thiểu 100 (ví dụ 85000 chứ không phải 85).',
            'traded_at.before_or_equal' => 'Ngày giao dịch không được vượt quá hôm nay.',
            'traded_at.required' => 'Hãy chọn ngày giao dịch.',
            'traded_at.date' => 'Ngày giao dịch không hợp lệ.',
            'stock_symbol.required' => 'Hãy nhập mã cổ phiếu.',
            'stock_symbol.max' => 'Mã cổ phiếu tối đa 12 ký tự.',
            'quantity.required' => 'Hãy nhập số lượng.',
            'quantity.integer' => 'Số lượng phải là số nguyên.',
            'quantity.max' => 'Số lượng quá lớn.',
            'price.required' => 'Hãy nhập giá (VNĐ / cổ phiếu).',
            'price.numeric' => 'Giá phải là một số.',
            'price.max' => 'Giá quá lớn, hãy kiểm tra lại (tính theo VNĐ).',
            'fee.numeric' => 'Phí phải là một số.',
            'fee.min' => 'Phí không được âm.',
            'fee.max' => 'Phí quá lớn, hãy kiểm tra lại số lượng và giá.',
            'notes.max' => 'Ghi chú tối đa 500 ký tự.',
        ]);

        $result = $this->ledger->trade($id, Auth::id(), $validated);

        if (! $result['ok']) {
            TransformerResponse::abortIf($result['status'] === TransformerResponse::HTTP_NOT_FOUND, TransformerResponse::HTTP_NOT_FOUND);

            return redirect()->route('portfolio.show', $id)->withErrors(['error' => $result['message']])->withInput();
        }

        ActivityLogger::log('portfolio_trade', $result['message'], ['portfolio_id' => $id, 'type' => $validated['type'], 'symbol' => $validated['stock_symbol']]);

        return TransformerResponse::redirectSuccess('portfolio.show', $result['message'], [$id]);
    }

    /** Undo the newest transaction of a symbol. */
    public function destroyTransaction(int $transactionId): RedirectResponse
    {
        $tx = PortfolioTransaction::find($transactionId);
        $portfolioId = $tx?->portfolio_id;

        $result = $this->ledger->undo($transactionId, Auth::id());

        if (! $result['ok']) {
            TransformerResponse::abortIf($result['status'] === TransformerResponse::HTTP_NOT_FOUND, TransformerResponse::HTTP_NOT_FOUND);

            return redirect()->route('portfolio.show', $portfolioId)->withErrors(['error' => $result['message']]);
        }

        return TransformerResponse::redirectSuccess('portfolio.show', $result['message'], [$result['portfolio_id']]);
    }

    /** CSV of the ledger (UTF-8 with BOM). */
    public function exportTransactions(int $id): StreamedResponse
    {
        $portfolio = $this->portfolioService->getPortfolioById($id, Auth::id()) ?? TransformerResponse::abortWith(TransformerResponse::HTTP_NOT_FOUND);
        $rows = $this->ledger->history($portfolio->id, 5000)->sortBy([['traded_at', 'asc'], ['id', 'asc']]);
        $name = Str::slug($portfolio->name) ?: 'portfolio';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Ngày', 'Mã', 'Loại', 'Số lượng', 'Giá', 'Giá trị', 'Phí + thuế', 'Giá vốn BQ', 'Lãi/Lỗ đã chốt (₫)', 'Ghi chú']);
            foreach ($rows as $t) {
                fputcsv($out, [
                    $t->traded_at->format('Y-m-d'), $t->stock_symbol, $t->isSell() ? 'Bán' : 'Mua', $t->quantity,
                    round($t->price), round($t->gross), round($t->fee),
                    $t->cost_basis !== null ? round($t->cost_basis) : '', $t->realized_pl !== null ? round($t->realized_pl) : '', $t->notes,
                ]);
            }
            fclose($out);
        }, "transactions-{$name}-".now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Get rebalance suggestions (AJAX)
     */
    public function getRebalanceSuggestions(int $id): JsonResponse
    {
        try {
            $suggestions = $this->portfolioService->getRebalanceSuggestions($id, Auth::id());

            if ($suggestions === null) {
                return TransformerResponse::forbidden(TransformerResponse::PORTFOLIO_NOT_FOUND_MESSAGE);
            }

            return TransformerResponse::success(extra: ['suggestions' => $suggestions]);
        } catch (Exception $e) {
            return TransformerResponse::serverError('Có lỗi xảy ra khi tạo gợi ý rebalance.');
        }
    }
}
