<?php

return [
    'enabled' => env('AI_ENABLED', false),
    'provider' => env('AI_PROVIDER', 'dummy'), // gemini, openai, dummy
    'api_key' => env('AI_API_KEY', ''),
    'gemini' => [
        'model' => env('AI_GEMINI_MODEL', 'gemini-2.0-flash'),
        'timeout' => env('AI_GEMINI_TIMEOUT', 30),
    ],
];

