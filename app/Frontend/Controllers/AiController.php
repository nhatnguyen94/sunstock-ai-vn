<?php

namespace App\Frontend\Controllers;

use App\Frontend\Services\AiService;
use App\Frontend\Services\AiUsageService;
use App\Models\AiRequest;
use App\Support\TransformerResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiController extends Controller
{
    public function predict(Request $request, AiService $aiService, AiUsageService $usage)
    {
        $user = Auth::user();
        $startedAt = microtime(true);
        if ($user && $refusal = $usage->refusal($user)) {
            $usage->record($user, AiRequest::KIND_PREDICT, AiRequest::STATUS_REFUSED, null, $startedAt);

            return TransformerResponse::failed($refusal['message'], $refusal['status'], extra: ['error' => true]);
        }

        $prompt = 'Dự đoán xu hướng thị trường chứng khoán Việt Nam tuần này. Nêu cụ thể các yếu tố tác động và khuyến nghị ngắn gọn cho nhà đầu tư.';
        $result = $aiService->predictMarket($prompt, $user);
        $usage->record($user, AiRequest::KIND_PREDICT, $result === null ? AiRequest::STATUS_ERROR : AiRequest::STATUS_OK, $aiService->lastModel(), $startedAt);

        if ($result === null) {
            return TransformerResponse::serviceUnavailable($aiService->unavailableMessage('vi'), ['error' => true]);
        }

        return TransformerResponse::success(extra: ['result' => $result]);
    }
}
