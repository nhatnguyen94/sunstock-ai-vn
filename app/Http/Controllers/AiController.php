<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AiService;

class AiController extends Controller
{
    public function predict(Request $request, AiService $aiService)
    {
        // Nếu chưa đăng nhập, chỉ cho phép 1 lần (có thể kiểm tra bằng session hoặc cookie nếu muốn chặt chẽ hơn)
        // Ở đây chỉ demo, luôn trả về kết quả
        $prompt = "Dự đoán thị trường chứng khoán Việt Nam tuần này.";
        $result = $aiService->predictMarket($prompt, Auth::user());
        return response()->json(['result' => $result]);
    }
}