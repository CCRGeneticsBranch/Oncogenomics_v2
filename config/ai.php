<?php

$legacyEndpoint = strtolower(trim((string) env('LLM_ENDPOINT', '')));
$legacyProvider = strtolower(trim((string) env('LLM_PROVIDER', 'gemini')));
$inferredProvider = str_contains($legacyEndpoint, 'api.groq.com') ? 'groq' : $legacyProvider;

return [
    'default' => env('AI_PROVIDER', $inferredProvider),
    'default_for_images' => 'gemini',
    'default_for_audio' => 'openai',
    'default_for_transcription' => 'openai',
    'default_for_embeddings' => 'openai',
    'default_for_reranking' => 'cohere',

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY', env('LLM_API_KEY')),
            'url' => env('ANTHROPIC_URL', env('ANTHROPIC_ENDPOINT', 'https://api.anthropic.com/v1')),
        ],
        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY', env('LLM_API_KEY')),
            'url' => rtrim((string) env('GEMINI_URL', env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta')), '/').'/',
        ],
        'groq' => [
            'driver' => 'groq',
            'key' => env('GROQ_API_KEY', env('LLM_API_KEY')),
            'url' => env('GROQ_URL', env('LLM_ENDPOINT', 'https://api.groq.com/openai/v1')),
        ],
        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY', env('LLM_API_KEY')),
            'url' => env('OPENAI_URL', env('OPENAI_ENDPOINT', 'https://api.openai.com/v1')),
        ],
    ],
];
