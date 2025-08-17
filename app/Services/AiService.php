<?php
// app/Services/AiService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiService
{
    public function askOllama($prompt, $model = 'mistral')
    {
        $response = Http::timeout(180)->post('http://localhost:11434/api/generate', [
            'model' => $model,
            'prompt' => $prompt,
            'stream' => false,
            // 'options' => ['num_predict' => 128]
        ]);
        return $response->json('response');
    }
    }