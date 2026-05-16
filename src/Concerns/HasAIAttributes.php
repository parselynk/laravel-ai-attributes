<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes\Concerns;

use Illuminate\Foundation\Bus\PendingDispatch;
use Parselynk\AiAttributes\AIManager;
use Parselynk\AiAttributes\Contracts\AIDriver;
use Parselynk\AiAttributes\Exceptions\AIAttributeException;
use Parselynk\AiAttributes\Jobs\GenerateAiAttributeJob;
use Parselynk\AiAttributes\Support\PromptCache;

/**
 * Adds AI-powered computed attributes to an Eloquent model.
 *
 * Declare a `$aiAttributes` array. Each entry is either a prompt string or
 * a config array with at minimum a `prompt` key:
 *
 *     protected $aiAttributes = [
 *         'summary' => 'Summarize this in 2 sentences',
 *
 *         'tags' => [
 *             'prompt' => 'Return 3-5 topic tags as a JSON array of strings',
 *             'format' => 'json',
 *         ],
 *
 *         'meta_description' => [
 *             'prompt'  => 'Write an SEO meta description in under 160 chars',
 *             'persona' => 'You are an SEO expert.',
 *             'driver'  => 'openai',
 *             'model'   => 'gpt-4o',
 *         ],
 *     ];
 *
 *     $article->ai_summary;          // string
 *     $article->ai_tags;             // array
 *     $article->ai_meta_description; // string from gpt-4o
 *
 * @property-read array<string, string|array<string, mixed>> $aiAttributes
 */
trait HasAIAttributes
{
    /**
     * Runtime persona override, applied to the next AI attribute read and then cleared.
     * Set via `aiPersona()`. NOT persisted with the model.
     */
    protected ?string $aiPersonaOverride = null;

    public function getAttribute($key)
    {
        if (is_string($key) && $this->isAiAttribute($key)) {
            return $this->resolveAiAttribute($this->stripAiPrefix($key));
        }

        return parent::getAttribute($key);
    }

    public function generateAiAttribute(string $attribute): mixed
    {
        return $this->resolveAiAttribute($attribute);
    }

    public function forgetAiAttribute(string $attribute): bool
    {
        return app(PromptCache::class)->forget(
            $this->aiCacheInputs($attribute, $this->resolveAttributeConfig($attribute)),
        );
    }

    /**
     * Override the persona for the NEXT AI attribute read.
     * The override clears automatically after one read.
     *
     * Heads-up: every distinct persona produces a separate cache entry.
     * If your persona varies per request, expect cache growth — use a short
     * TTL or set `cache.enabled => false` for that attribute's use case.
     */
    public function aiPersona(string $persona): static
    {
        $this->aiPersonaOverride = $persona;

        return $this;
    }

    /**
     * Returns the cached value (already cast to its format) without triggering an AI call.
     * Returns null if the value is not yet cached.
     */
    public function aiAttributeOrNull(string $attribute): mixed
    {
        $config = $this->resolveAttributeConfig($attribute);

        if ($this->aiPersonaOverride !== null) {
            $config['persona'] = $this->aiPersonaOverride;
        }

        $raw = app(PromptCache::class)->get($this->aiCacheInputs($attribute, $config));

        if ($raw === null) {
            return null;
        }

        return $this->castFormat($raw, (string) ($config['format'] ?? 'text'));
    }

    /**
     * True if a cached value already exists for this attribute, false otherwise.
     * Does NOT trigger an AI call.
     */
    public function hasAiAttribute(string $attribute): bool
    {
        $config = $this->resolveAttributeConfig($attribute);

        if ($this->aiPersonaOverride !== null) {
            $config['persona'] = $this->aiPersonaOverride;
        }

        return app(PromptCache::class)->has($this->aiCacheInputs($attribute, $config));
    }

    /**
     * Dispatch the AI generation to the queue. The model must already be persisted
     * because the queue job rehydrates from the database.
     *
     * Any pending `aiPersona()` override is consumed and forwarded to the job.
     */
    public function generateAiAttributeAsync(string $attribute): PendingDispatch
    {
        if (! $this->exists) {
            throw AIAttributeException::cannotQueueUnsavedModel(static::class);
        }

        // Validate the attribute exists before dispatching, so errors surface immediately
        // rather than from a worker.
        $this->resolveAttributeConfig($attribute);

        $persona = $this->aiPersonaOverride;
        $this->aiPersonaOverride = null;

        return GenerateAiAttributeJob::dispatch($this, $attribute, $persona);
    }

    protected function isAiAttribute(string $key): bool
    {
        if (! str_starts_with($key, 'ai_')) {
            return false;
        }

        return array_key_exists($this->stripAiPrefix($key), $this->aiAttributes ?? []);
    }

    protected function stripAiPrefix(string $key): string
    {
        return substr($key, 3);
    }

    protected function resolveAiAttribute(string $attribute): mixed
    {
        $config = $this->resolveAttributeConfig($attribute);

        if ($this->aiPersonaOverride !== null) {
            $config['persona'] = $this->aiPersonaOverride;
            $this->aiPersonaOverride = null;
        }

        $context = $this->aiAttributeContext();

        $raw = app(PromptCache::class)->remember(
            $this->aiCacheInputs($attribute, $config, $context),
            fn () => $this->aiDriver($config['driver'] ?? null)->generate(
                $config['prompt'],
                $context,
                $this->aiDriverOptions($config),
            ),
        );

        return $this->castFormat($raw, (string) ($config['format'] ?? 'text'));
    }

    protected function resolveAttributeConfig(string $attribute): array
    {
        $raw = ($this->aiAttributes ?? [])[$attribute] ?? null;

        if ($raw === null) {
            throw AIAttributeException::undefinedAttribute(static::class, $attribute);
        }

        if (is_string($raw)) {
            return ['prompt' => $raw];
        }

        if (is_array($raw) && isset($raw['prompt']) && is_string($raw['prompt'])) {
            return $raw;
        }

        throw AIAttributeException::invalidAttributeConfig(static::class, $attribute);
    }

    protected function aiCacheInputs(string $attribute, array $config, ?array $context = null): array
    {
        return [
            'class' => static::class,
            'attribute' => $attribute,
            'prompt' => $config['prompt'],
            'persona' => $config['persona'] ?? null,
            'driver' => $config['driver'] ?? null,
            'model' => $config['model'] ?? null,
            'max_tokens' => $config['max_tokens'] ?? null,
            'format' => $config['format'] ?? 'text',
            'attributes' => $context ?? $this->aiAttributeContext(),
        ];
    }

    protected function aiDriverOptions(array $config): array
    {
        return array_filter(
            [
                'persona' => $config['persona'] ?? null,
                'model' => $config['model'] ?? null,
                'max_tokens' => $config['max_tokens'] ?? null,
            ],
            fn ($value) => $value !== null,
        );
    }

    protected function aiAttributeContext(): array
    {
        $attributes = $this->attributesToArray();

        // Timestamps shouldn't affect AI output and serialize unstably across
        // create/refresh boundaries (different microsecond precision), so omit
        // them from the cache key.
        if ($this->usesTimestamps()) {
            unset(
                $attributes[$this->getCreatedAtColumn()],
                $attributes[$this->getUpdatedAtColumn()],
            );
        }

        return $attributes;
    }

    protected function aiDriver(?string $name = null): AIDriver
    {
        return app(AIManager::class)->driver($name);
    }

    protected function castFormat(string $raw, string $format): mixed
    {
        return match ($format) {
            'text' => $raw,
            'json' => $this->castJson($raw),
            'number' => $this->castNumber($raw),
            'bool' => $this->castBool($raw),
            default => throw AIAttributeException::unsupportedFormat($format),
        };
    }

    protected function castJson(string $raw): mixed
    {
        $cleaned = trim($raw);

        if (preg_match('/^```(?:json)?\s*\n?(.*?)\n?```$/s', $cleaned, $matches)) {
            $cleaned = trim($matches[1]);
        }

        $decoded = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw AIAttributeException::invalidJson($raw);
        }

        return $decoded;
    }

    protected function castNumber(string $raw): int|float
    {
        $cleaned = trim($raw);

        if (! is_numeric($cleaned)) {
            throw AIAttributeException::invalidNumber($raw);
        }

        return str_contains($cleaned, '.') ? (float) $cleaned : (int) $cleaned;
    }

    protected function castBool(string $raw): bool
    {
        $cleaned = strtolower(trim($raw));

        return match ($cleaned) {
            'yes', 'true', '1', 'y', 't' => true,
            'no', 'false', '0', 'n', 'f' => false,
            default => throw AIAttributeException::invalidBool($raw),
        };
    }
}
