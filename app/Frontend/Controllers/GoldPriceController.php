<?php

namespace App\Frontend\Controllers;

use App\Frontend\Services\GoldPriceService;
use App\Support\TransformerResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        return $result ? TransformerResponse::success(extra: $result) : TransformerResponse::notFound();
    }

    /** "Làm mới" button: fetch now (rate-limited). */
    public function refresh(): JsonResponse
    {
        if (! Auth::check()) {
            return TransformerResponse::unauthorized('Vui lòng đăng nhập để làm mới giá.', ['login_url' => route('login')]);
        }

        $result = $this->service->refresh();

        if (isset($result['error'])) {
            return TransformerResponse::failed($result['error'], ($result['cooldown'] ?? false) ? TransformerResponse::HTTP_TOO_MANY_REQUESTS : TransformerResponse::HTTP_BAD_GATEWAY);
        }

        return TransformerResponse::success($result['count'] > 0 ? "Đã cập nhật {$result['count']} mức giá mới." : 'Giá chưa thay đổi so với lần cập nhật trước.');
    }
}
