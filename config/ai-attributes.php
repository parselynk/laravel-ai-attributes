<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | The name of the AI provider (as configured in `config/ai.php` from the
    | official `laravel/ai` package) to use when an attribute doesn't specify
    | one explicitly. Set to null to use laravel/ai's own default.
    |
    | Examples: "openai", "anthropic", "ollama", "gemini"
    |
    */

    'default_provider' => env('AI_ATTRIBUTES_PROVIDER'),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Generated values are cached by a hash of (model class, attribute key,
    | prompt, provider, model, persona, serialized model attributes) so that
    | identical inputs never hit the AI twice. Set `store` to null to use the
    | application's default cache.
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
    | Default Timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | Applied to every AI call. Individual attributes can override via the
    | `timeout` key in their `$aiAttributes` config.
    |
    */

    'timeout' => (int) env('AI_ATTRIBUTES_TIMEOUT', 60),

];
