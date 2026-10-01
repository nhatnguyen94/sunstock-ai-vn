<?php

/*
|--------------------------------------------------------------------------
| Per-user limits for the AI features
|--------------------------------------------------------------------------
|
| AI chat and the market prediction are only for signed-in users and are limited per account (not per IP) so a
| single person cannot spam the paid Groq API. Change the numbers in .env, no code change needed:
|
|   AI_PREDICT_INTERVAL_MINUTES  one prediction per this many minutes          (default 15)
|   AI_CHAT_WINDOW_MINUTES       length of the chat window in minutes          (default 5)
|   AI_CHAT_MAX_QUESTIONS        questions allowed inside one chat window      (default 5)
|
*/

return [
    'predict' => [
        'interval_minutes' => max(1, (int) env('AI_PREDICT_INTERVAL_MINUTES', 15)),
    ],

    'chat' => [
        'window_minutes' => max(1, (int) env('AI_CHAT_WINDOW_MINUTES', 5)),
        'max_questions' => max(1, (int) env('AI_CHAT_MAX_QUESTIONS', 5)),
    ],
];
