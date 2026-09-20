<?php

namespace App\Frontend\Controllers;

use App\Frontend\Services\GoldPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoldPriceController extends Controller
{
    public function __construct(private readonly GoldPriceService $service) {}

    public function index(): View
    {
        return view('gold.index', $this->service->page());
    }

    /** Chart series for one product (`id` = a gold_prices row of that product), e.g. /gold/history/12?range=7D */
    public function history(Request $request, int $id): JsonResponse
    {
        $result = $this->service->history($id, (string) $request->query('range', '7D'));

        return $result
            ? response()->json(['success' => true] + $result)
            : response()->json(['success' => false, 'message' => 'Không tìm thấy dữ liệu.'], 404);
    }

    /** "Làm mới" button: fetch now (rate-limited). */
    public function refresh(): JsonResponse
    {
        $result = $this->service->refresh();

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'message' => $result['error']], ($result['cooldown'] ?? false) ? 429 : 502);
        }

        return response()->json([
            'success' => true,
            'message' => $result['count'] > 0 ? "Đã cập nhật {$result['count']} mức giá mới." : 'Giá chưa thay đổi so với lần cập nhật trước.',
        ]);
    }
}
