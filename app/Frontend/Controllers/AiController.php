<?php

namespace App\Frontend\Controllers;

use App\Frontend\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiController extends Controller
{
    public function predict(Request $request, AiService $aiService)
    {
        $prompt = 'Dự đoán xu hướng thị trường chứng khoán Việt Nam tuần này. Nêu cụ thể các yếu tố tác động và khuyến nghị ngắn gọn cho nhà đầu tư.';
        $result = $aiService->predictMarket($prompt, Auth::user());

        return response()->json(['result' => $result]);
    }
}
