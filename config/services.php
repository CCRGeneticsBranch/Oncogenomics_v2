<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'llm' => [
        'provider' => env('LLM_PROVIDER', 'gemini'),
        'api_key' => env('LLM_API_KEY'),
        'model' => env('LLM_MODEL'),
        'endpoint' => env('LLM_ENDPOINT'),
        'temperature' => env('LLM_TEMPERATURE', 0),
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY', env('LLM_API_KEY')),
            'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
            'endpoint' => env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta'),
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY', env('LLM_API_KEY')),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'endpoint' => env('OPENAI_ENDPOINT', 'https://api.openai.com/v1'),
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY', env('LLM_API_KEY')),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-latest'),
            'endpoint' => env('ANTHROPIC_ENDPOINT', 'https://api.anthropic.com/v1'),
            'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        ],
    ],

];
