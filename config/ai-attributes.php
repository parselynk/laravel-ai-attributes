<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI driver
    |--------------------------------------------------------------------------
    |
    | The driver used when a model resolves an `ai_*` attribute. Must match a
    | key under `drivers` below, or a driver registered via
    | `AIManager::extend()`.
    |
    */

    'default' => env('AI_ATTRIBUTES_DRIVER', 'claude'),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Generated values are cached by a hash of (model class, attribute key,
    | prompt, serialized model attributes) so identical inputs never hit the
    | AI twice. Set `store` to null to use the application's default cache.
    |
    */

    'cache' => [
        'enabled' => env('AI_ATTRIBUTES_CACHE_ENABLED', true),
        'store' => env('AI_ATTRIBUTES_CACHE_STORE'),
        'ttl' => (int) env('AI_ATTRIBUTES_CACHE_TTL', 60 * 60 * 24 * 30),
        'prefix' => env('AI_ATTRIBUTES_CACHE_PREFIX', 'ai_attr'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    */

    'drivers' => [

        'claude' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
            'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 1024),
            'timeout' => (int) env('ANTHROPIC_TIMEOUT', 30),
            'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
            'retries' => [
                'max_attempts' => (int) env('ANTHROPIC_RETRIES_MAX', 3),
                'base_delay_ms' => (int) env('ANTHROPIC_RETRIES_DELAY_MS', 1000),
            ],
        ],

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 1024),
            'timeout' => (int) env('OPENAI_TIMEOUT', 30),
            'retries' => [
                'max_attempts' => (int) env('OPENAI_RETRIES_MAX', 3),
                'base_delay_ms' => (int) env('OPENAI_RETRIES_DELAY_MS', 1000),
            ],
        ],

    ],

];
