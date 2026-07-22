# Changelog

All notable changes to `parselynk/laravel-ai-attributes` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] — Refactored on top of the official Laravel AI SDK

### Breaking changes

- **Requires `laravel/ai` ^0.10** — installed automatically as a Composer dependency.
- **Requires PHP 8.3+** and **Laravel 12.62+ or 13.x**. Laravel 11 support dropped.
- Provider configuration now lives in `config/ai.php` (from `laravel/ai`), NOT in this package's config. Set `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `OLLAMA_URL` etc. there.
- `$aiAttributes` schema:
  - `driver` renamed to `provider` (matches `laravel/ai` terminology).
  - `max_tokens` removed (laravel/ai handles it per-provider default).
  - `temperature` moved to laravel/ai's agent-class attributes (out of scope for v2 per-attribute config).
- Env vars renamed. Old: `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `OLLAMA_MODEL`, `AI_ATTRIBUTES_DRIVER`.
  New: keep provider-specific vars, plus `AI_ATTRIBUTES_PROVIDER` (default provider name).
- Package classes removed (users should not have depended on these directly):
  - `Parselynk\AiAttributes\AIManager`
  - `Parselynk\AiAttributes\Contracts\AIDriver`
  - `Parselynk\AiAttributes\Drivers\{Claude,OpenAI,Ollama}Driver`
  - `Parselynk\AiAttributes\Support\HttpRetrier`

### Added

- Full support for all 14 `laravel/ai` providers (OpenAI, Anthropic, Gemini, Groq, Mistral, DeepSeek, xAI, Ollama, Azure OpenAI, Cohere, OpenRouter, Jina, VoyageAI, ElevenLabs).
- README rewritten to reflect the new architecture.

### Improved

- ~500 lines of driver + retry code deleted. Simpler package, less to maintain.
- Testing now uses `AnonymousAgent::fake()` from `laravel/ai` — one consistent way to mock every provider.
- Cache key now includes `provider` (renamed from `driver`), so changing provider invalidates cache correctly.

## [1.0.0] — 2026-05-16

Initial public release.

### Added

- `HasAIAttributes` trait that exposes `ai_*` magic accessors on any Eloquent model.
- Claude, OpenAI, and Ollama drivers built on Laravel's `Http` facade.
- `AIManager` driver resolver (extends `Illuminate\Support\Manager`).
- `PromptCache` layer keyed by model class + attribute + prompt + serialized attributes.
- Per-attribute config: persona, model, driver, max_tokens, format, temperature (Ollama).
- Format casting: `text` (default), `json` (auto-decoded), `number`, `bool`.
- Runtime persona override via `$model->aiPersona('...')->ai_attr`.
- Retries with exponential backoff (429, 5xx, connection errors).
- Queued generation via `Jobs\GenerateAiAttributeJob`.
- Artisan command: `php artisan ai:regenerate {model} [options]`.
- Peek methods that don't trigger AI: `hasAiAttribute()`, `aiAttributeOrNull()`.
- Publishable config at `config/ai-attributes.php`.
- Pest test suite (80 tests, 133 assertions).
