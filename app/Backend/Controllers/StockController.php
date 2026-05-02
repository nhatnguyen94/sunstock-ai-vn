<?php

namespace App\Backend\Controllers;

use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StockController extends Controller
{
    /**
     * Hiển thị danh sách stocks
     * Admin và AdminSupport có quyền
     */
    public function index(Request $request)
    {
        // Kiểm tra quyền
        if (!Gate::allows('manage-features')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        $stocks = Stock::query()
            ->when($request->search, function($query, $search) {
                $query->where('symbol', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            })
            ->when($request->exchange, function($query, $exchange) {
                $query->where('exchange', $exchange);
            })
            ->orderBy('symbol')
            ->paginate(20);

        // Lấy danh sách exchange để filter
        $exchanges = Stock::distinct()->pluck('exchange')->filter();

        return view('backend.stocks.index', compact('stocks', 'exchanges'));
    }

    /**
     * Hiển thị form tạo stock mới
     */
    public function create()
    {
        if (!Gate::allows('manage-features')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        return view('backend.stocks.create');
    }

    /**
     * Lưu stock mới
     */
    public function store(Request $request)
    {
        if (!Gate::allows('manage-features')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        $request->validate([
            'symbol' => 'required|string|max:10|unique:stocks',
            'name' => 'required|string|max:255',
            'exchange' => 'required|string|max:50',
            'industry' => 'nullable|string|max:100',
            'market_cap' => 'nullable|numeric',
            'description' => 'nullable|string',
        ]);

        Stock::create($request->all());

        return redirect()->route('admin.stocks.index')
            ->with('success', 'Stock đã được tạo thành công!');
    }

    /**
     * Hiển thị chi tiết stock
     */
    public function show(Stock $stock)
    {
        if (!Gate::allows('manage-features')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        $stock->load('prices');
        return view('backend.stocks.show', compact('stock'));
    }

    /**
     * Hiển thị form chỉnh sửa stock
     */
    public function edit(Stock $stock)
    {
        if (!Gate::allows('manage-features')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        return view('backend.stocks.edit', compact('stock'));
    }

    /**
     * Cập nhật stock
     */
    public function update(Request $request, Stock $stock)
    {
        if (!Gate::allows('manage-features')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        $request->validate([
            'symbol' => 'required|string|max:10|unique:stocks,symbol,' . $stock->id,
            'name' => 'required|string|max:255',
            'exchange' => 'required|string|max:50',
            'industry' => 'nullable|string|max:100',
            'market_cap' => 'nullable|numeric',
            'description' => 'nullable|string',
        ]);

        $stock->update($request->all());

        return redirect()->route('admin.stocks.index')
            ->with('success', 'Stock đã được cập nhật thành công!');
    }

    /**
     * Xóa stock
     */
    public function destroy(Stock $stock)
    {
        if (!Gate::allows('manage-features')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        $stock->delete();

        return redirect()->route('admin.stocks.index')
            ->with('success', 'Stock đã được xóa thành công!');
    }

    /**
     * Cập nhật giá stock từ API
     */
    public function updatePrices()
    {
        if (!Gate::allows('manage-features')) {
            return redirect()->route('admin.dashboard')->with('error', 'Bạn không có quyền truy cập tính năng này.');
        }

        // Thực thi Python script để cập nhật giá
        // Đây chỉ là ví dụ - trong thực tế sẽ gọi script Python
        
        return redirect()->route('admin.stocks.index')
            ->with('success', 'Đã bắt đầu cập nhật giá stock. Quá trình này có thể mất vài phút.');
    }
}