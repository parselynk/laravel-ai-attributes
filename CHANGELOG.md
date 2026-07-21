# Changelog

All notable changes to `parselynk/laravel-ai-attributes` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `HasAIAttributes` trait that exposes `ai_*` magic accessors on any Eloquent model.
- `AIManager` driver resolver (extends `Illuminate\Support\Manager`).
- Claude and OpenAI drivers built on Laravel's `Http` facade.
- **Ollama driver** for local LLM inference — runs against any Ollama server (local or remote) with `temperature: 0` by default for predictable structured output.
- `PromptCache` layer keyed by model class + attribute + prompt + serialized attributes.
- Per-attribute config: persona, model, driver, max_tokens, format, temperature.
- Format casting: `text` (default), `json` (auto-decoded), `number`, `bool`.
- Runtime persona override via `$model->aiPersona('...')->ai_attr`.
- Retries with exponential backoff on 429/5xx/network errors.
- Queued generation via `GenerateAiAttributeJob`.
- Artisan command: `php artisan ai:regenerate {model}`.
- Peek methods that don't trigger AI: `hasAiAttribute()`, `aiAttributeOrNull()`.
- Publishable config at `config/ai-attributes.php`.
- Pest test suite (Orchestra Testbench) — 80 tests, 133 assertions.
