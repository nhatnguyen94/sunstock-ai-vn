<?php
// app/Services/AiService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiService
{
    public function ask($prompt, $lang = 'vi', $model = 'openai/gpt-oss-20b:free')
    {
        $apiKey = env('OPENROUTER_API_KEY');
        $referer = config('app.url', 'http://127.0.0.1:8000');
        $systemPrompt = $lang === 'en'
            ? "You are a financial assistant for Vietnamese stocks. Answer concisely and accurately in English."
            : "Bạn là trợ lý tài chính chuyên về chứng khoán Việt Nam. Trả lời ngắn gọn, chính xác, bằng tiếng Việt.";
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'HTTP-Referer' => $referer,
            'X-Title' => 'Sun Stock AI'
        ])->post('https://openrouter.ai/api/v1/chat/completions', [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt]
            ]
        ]);
        return $response->json('choices.0.message.content');
    }

    public function predictMarket($prompt, $user = null)
    {
        // Có thể tuỳ chỉnh prompt theo user nếu muốn
        return $this->ask($prompt, 'vi');
    }
}