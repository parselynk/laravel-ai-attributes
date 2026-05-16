# Changelog

All notable changes to `parselynk/laravel-ai-attributes` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `HasAIAttributes` trait that exposes `ai_*` magic accessors on any Eloquent model.
- `AIManager` driver resolver (extends `Illuminate\Support\Manager`).
- Claude and OpenAI drivers built on Laravel's `Http` facade.
- `PromptCache` layer keyed by model class + attribute + prompt + serialized attributes.
- Publishable config at `config/ai-attributes.php`.
- Pest test suite (Orchestra Testbench).
