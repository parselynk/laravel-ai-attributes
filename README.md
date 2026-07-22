# laravel-ai-attributes

[![Tests](https://github.com/parselynk/laravel-ai-attributes/actions/workflows/tests.yml/badge.svg)](https://github.com/parselynk/laravel-ai-attributes/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/parselynk/laravel-ai-attributes.svg)](https://packagist.org/packages/parselynk/laravel-ai-attributes)
[![Total Downloads](https://img.shields.io/packagist/dt/parselynk/laravel-ai-attributes.svg)](https://packagist.org/packages/parselynk/laravel-ai-attributes)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)

Add AI-powered computed attributes to any Eloquent model with a single trait — built on top of the official [Laravel AI SDK](https://laravel.com/ai).

```php
class Article extends Model
{
    use HasAIAttributes;

    protected $aiAttributes = [
        'summary' => 'Summarize this in 2 sentences',
        'tags'    => [
            'prompt' => 'Return 3-5 topic tags as a JSON array',
            'format' => 'json',
        ],
    ];
}

$article = Article::find(1);

$article->ai_summary;  // → "Laravel 13 ships with..."
$article->ai_tags;     // → ["laravel", "php", "release-notes", ...] (real array)
```

The first read calls the AI provider via `laravel/ai`; subsequent reads with the same input come from cache.

---

## Why this package?

The official [`laravel/ai`](https://laravel.com/ai) package gives you a unified API for talking to any AI provider (OpenAI, Anthropic, Ollama, Gemini, and more).

**This package sits one layer above it**, adding the parts you'd otherwise write yourself for every Eloquent model:

| `laravel/ai` gives you | This package adds |
|---|---|
| Multi-provider AI calls | `ai_*` attribute magic on any Eloquent model |
| Text generation | Content-hash caching (same input → free) |
| Structured output | Automatic casting to text/json/number/bool |
| Agents | Per-attribute persona, provider, model, timeout |
| Streaming, embeddings, images, RAG | Queued generation, Artisan `ai:regenerate` |

If you want AI in your Eloquent models with almost zero boilerplate — this is for you.

---

## Requirements

- PHP **8.3+**
- Laravel **12.62+** (or 13.x)
- [`laravel/ai`](https://packagist.org/packages/laravel/ai) **v0.10+** (installed as a dependency)

---

## Installation

```bash
composer require parselynk/laravel-ai-attributes
```

Publish the config:

```bash
php artisan vendor:publish --tag=ai-attributes-config
```

Configure at least one AI provider in `config/ai.php` (from `laravel/ai`) — for example, set `OPENAI_API_KEY` in `.env`.

Then pick a default provider for this package in your `.env`:

```dotenv
AI_ATTRIBUTES_PROVIDER=openai   # or "anthropic", "ollama", "gemini", ...
```

---

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
        'tags'    => [
            'prompt' => 'Return 3 to 5 topic tags as a JSON array of strings.',
            'format' => 'json',
        ],
    ];
}
```

### 2. Read the AI attributes

Each key in `$aiAttributes` is exposed with an `ai_` prefix:

```php
$article = Article::find(1);

$article->ai_summary;  // calls the AI, cached on subsequent reads
$article->ai_tags;     // returns an array (auto-decoded from JSON)
```

### 3. Manually regenerate, invalidate, or peek

```php
// Bypass magic — force generation:
$article->generateAiAttribute('summary');

// Drop cached value so the next read regenerates:
$article->forgetAiAttribute('summary');

// Peek without triggering an AI call:
$article->hasAiAttribute('summary');       // bool
$article->aiAttributeOrNull('summary');    // value or null

// Runtime persona override (auto-clears after one read):
$article->aiPersona('You are a poet.')->ai_summary;

// Queue the AI call:
$article->generateAiAttributeAsync('summary');

// Bulk regenerate from CLI:
php artisan ai:regenerate "App\Models\Article" --attribute=summary --queue
```

---

## Per-attribute config

Each `$aiAttributes` entry is either a prompt string or an array:

```php
protected $aiAttributes = [
    'summary' => 'Summarize in 2 sentences.',   // shorthand: prompt only

    'tags' => [
        'prompt' => 'Return 3-5 tags as JSON array.',
        'format' => 'json',
    ],

    'meta_description' => [
        'prompt'   => 'Write an SEO meta description under 160 chars.',
        'persona'  => 'You are an SEO expert.',
        'provider' => 'anthropic',    // any provider from config/ai.php
        'model'    => 'claude-sonnet-4-6',
        'timeout'  => 30,
    ],

    'is_clickbait' => [
        'prompt' => 'Is the title clickbait? Answer yes or no.',
        'format' => 'bool',
    ],
];
```

Available keys:

| Key | Type | Purpose |
|---|---|---|
| `prompt` | string | The instruction sent to the AI (**required**) |
| `persona` | string | System instructions ("You are a..."); becomes the agent's `instructions()` |
| `provider` | string | Which provider from `config/ai.php` (e.g. `openai`, `anthropic`, `ollama`) |
| `model` | string | Model name for the chosen provider |
| `timeout` | int | Seconds before the request times out |
| `format` | string | Output cast: `text` (default), `json`, `number`, or `bool` |

---

## Format casting

Return values are automatically cast based on `format`:

| Format | Cast to | Notes |
|---|---|---|
| `text` (default) | `string` | Trimmed |
| `json` | `array` / `object` | Also strips markdown code fences (```` ```json ```` wrappers) |
| `number` | `int` or `float` | Throws if response isn't numeric |
| `bool` | `true` / `false` | Accepts yes/no/true/false/1/0/y/n/t/f |

---

## Caching

A SHA-256 cache key is built from:

- the **model class**
- the **attribute key**
- the **prompt**, **persona**, **provider**, **model**, **format**
- the **model's attributes** (excluding timestamps)

If any of those change, the value is regenerated. If none change, the AI is never called twice.

Configure via `.env`:

```dotenv
AI_ATTRIBUTES_CACHE_ENABLED=true
AI_ATTRIBUTES_CACHE_STORE=redis
AI_ATTRIBUTES_CACHE_TTL=2592000    # 30 days in seconds
```

---

## Using Ollama (local, free, private)

1. Install and start Ollama:
   ```bash
   brew install ollama          # macOS
   brew services start ollama
   ```
2. Pull a model (recommended for structured output):
   ```bash
   ollama pull qwen2.5:7b
   ```
3. In `.env`:
   ```dotenv
   AI_ATTRIBUTES_PROVIDER=ollama
   OLLAMA_URL=http://localhost:11434
   ```

Ollama is one of the 14 providers supported by `laravel/ai` — this package just picks it up automatically.

---

## Testing

Tests use [Pest](https://pestphp.com) and [Orchestra Testbench](https://packages.tools/testbench). AI calls are faked via `laravel/ai`'s built-in fakes so the suite never touches a real provider.

```bash
composer install
composer test
```

In your own app's tests:

```php
use Laravel\Ai\AnonymousAgent;

AnonymousAgent::fake(['A predictable summary.']);

$article = new Article(['title' => 'Hi', 'body' => 'world']);

expect($article->ai_summary)->toBe('A predictable summary.');
```

---

## Roadmap

Shipped:

- **v1** — Core trait, own Claude + OpenAI + Ollama drivers, format casting, retries, queues, persona, artisan regenerate.
- **v2** — Refactored on top of the official `laravel/ai` SDK. Now supports 14 providers automatically. Simpler codebase, framework-aligned.

Coming:

- Filament admin UI (paid companion plugin — [parselynk/filament-ai-fields](#) — coming soon).
- Optional DB column persistence.
- Events: `AIAttributeGenerated`, `AIAttributeFailed`.
- Token-usage tracking hook.

---

## Contributing

Issues and PRs welcome. Run the test suite (`composer test`) and formatter (`composer format`) before submitting.

## Credits

- [Reza](https://github.com/parselynk)
- Built on [`laravel/ai`](https://github.com/laravel/ai)

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
