# laravel-ai-attributes

[![Tests](https://github.com/parselynk/laravel-ai-attributes/actions/workflows/tests.yml/badge.svg)](https://github.com/parselynk/laravel-ai-attributes/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/parselynk/laravel-ai-attributes.svg)](https://packagist.org/packages/parselynk/laravel-ai-attributes)
[![Total Downloads](https://img.shields.io/packagist/dt/parselynk/laravel-ai-attributes.svg)](https://packagist.org/packages/parselynk/laravel-ai-attributes)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)

Add AI-powered computed attributes to any Eloquent model with a single trait.

```php
class Article extends Model
{
    use HasAIAttributes;

    protected $aiAttributes = [
        'summary' => 'Summarize this in 2 sentences',
        'tags'    => 'Return 3-5 topic tags as JSON array',
    ];
}

$article = Article::find(1);

$article->ai_summary;  // → "Laravel 12 ships with..."
$article->ai_tags;     // → '["laravel", "php", "release-notes", ...]'
```

The first read calls the AI provider; subsequent reads with the same input come from cache.

---

## Why?

You've probably written this code five times already:

- "Summarize this article"
- "Suggest tags for this post"
- "Translate this product description"
- "Generate a meta-description for SEO"

Every one of those is the same shape: take some model attributes, send them with a prompt, get text back, cache the result. This package collapses all of that into one trait.

## Installation

```bash
composer require parselynk/laravel-ai-attributes
```

Publish the config:

```bash
php artisan vendor:publish --tag=ai-attributes-config
```

Set your API keys in `.env`:

```dotenv
ANTHROPIC_API_KEY=sk-ant-...
OPENAI_API_KEY=sk-...

# Pick the default driver:
AI_ATTRIBUTES_DRIVER=claude   # or "openai"
```

## Usage

### 1. Add the trait to a model

```php
use Illuminate\Database\Eloquent\Model;
use Parselynk\AiAttributes\Concerns\HasAIAttributes;

class Article extends Model
{
    use HasAIAttributes;

    protected $aiAttributes = [
        'summary' => 'Summarize this article in 2 sentences.',
        'tags'    => 'Return 3 to 5 topic tags as a JSON array of strings.',
    ];
}
```

### 2. Read the AI attributes

Each key in `$aiAttributes` is exposed with an `ai_` prefix:

```php
$article = Article::find(1);

$article->ai_summary;   // calls the AI, cached on subsequent reads
$article->ai_tags;
```

### 3. Manually regenerate or invalidate

```php
// Bypass the trait's magic and force a generation:
$article->generateAiAttribute('summary');

// Drop the cached value so the next read calls the AI again:
$article->forgetAiAttribute('summary');
```

## How caching works

A SHA-256 cache key is built from:

- the **model class** (`App\Models\Article`)
- the **attribute key** (`summary`)
- the **prompt** (the string from `$aiAttributes`)
- the **model attributes** at read time (`attributesToArray()`)

If any of those change, the value is regenerated. If none of them change, the AI is never called twice.

The cache uses your application's default cache store. Override per-app via `.env`:

```dotenv
AI_ATTRIBUTES_CACHE_ENABLED=true
AI_ATTRIBUTES_CACHE_STORE=redis
AI_ATTRIBUTES_CACHE_TTL=2592000   # 30 days, in seconds
```

## Available drivers

| Driver   | Provider           | Default model       |
|----------|--------------------|---------------------|
| `claude` | Anthropic          | `claude-sonnet-4-6` |
| `openai` | OpenAI             | `gpt-4o-mini`       |

Switch the default at runtime:

```php
config(['ai-attributes.default' => 'openai']);
```

### Adding a custom driver

The package uses Laravel's `Manager` pattern (the same one as `Cache`, `Queue`, `Mail`). Register a custom driver in any service provider:

```php
use Parselynk\AiAttributes\AIManager;
use Parselynk\AiAttributes\Contracts\AIDriver;

public function boot(): void
{
    $this->app->make(AIManager::class)->extend('mistral', function ($app) {
        return new MistralDriver(config('ai-attributes.drivers.mistral'));
    });
}
```

Your driver only needs to implement one method:

```php
class MistralDriver implements AIDriver
{
    public function __construct(protected array $config) {}

    public function generate(string $prompt, array $context = []): string
    {
        // Use Laravel's Http facade — the package itself does this for Claude/OpenAI.
        $response = Http::withToken($this->config['api_key'])
            ->post($this->config['base_url'].'/chat/completions', [
                'model' => $this->config['model'],
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

        return $response->json('choices.0.message.content');
    }
}
```

## Configuration

The published `config/ai-attributes.php` is fully commented. Highlights:

```php
return [
    'default' => env('AI_ATTRIBUTES_DRIVER', 'claude'),

    'cache' => [
        'enabled' => env('AI_ATTRIBUTES_CACHE_ENABLED', true),
        'store'   => env('AI_ATTRIBUTES_CACHE_STORE'),
        'ttl'     => (int) env('AI_ATTRIBUTES_CACHE_TTL', 60 * 60 * 24 * 30),
        'prefix'  => env('AI_ATTRIBUTES_CACHE_PREFIX', 'ai_attr'),
    ],

    'drivers' => [
        'claude' => [ /* api_key, base_url, model, max_tokens, timeout, version */ ],
        'openai' => [ /* api_key, base_url, model, max_tokens, timeout */ ],
    ],
];
```

## Testing

```bash
composer install
composer test
```

Tests use [Pest](https://pestphp.com) and [Orchestra Testbench](https://packages.tools/testbench). HTTP calls are faked with `Http::fake()` so the test suite never touches a real provider.

## Roadmap

This is **Phase 1** — core trait, drivers, caching. Coming up:

- **Phase 2** — Queued generation, per-attribute config (model, max_tokens, JSON mode), retries.
- **Phase 3** — Optional DB persistence, events, token-usage tracking.
- **Phase 4** — Gemini, Ollama, Groq, OpenRouter drivers · streaming · Nova/Filament fields.

A goal of Phase 4 is to ship a first-class Ollama driver so `laravel-ai-attributes` becomes one of the most ergonomic ways to use a local LLM from PHP.

## Contributing

Issues and PRs welcome. Run the test suite (`composer test`) and the formatter (`composer format`) before submitting.

## Credits

- [Reza](https://github.com/parselynk)
- [All contributors](../../contributors)

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
