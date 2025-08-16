<?php
// app/Services/AiService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiService
{
    public function askOllama($prompt, $model = 'gemma3:1b')
    {
        $response = Http::timeout(60)->post('http://localhost:11434/api/generate', [
            'model' => $model,
            'prompt' => $prompt,
            'stream' => false,
            'options' => ['num_predict' => 128]
        ]);
        return $response->json('response');
    }
    }