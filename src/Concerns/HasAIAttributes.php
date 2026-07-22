<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes\Concerns;

use Illuminate\Foundation\Bus\PendingDispatch;
use Laravel\Ai\AnonymousAgent;
use Parselynk\AiAttributes\Exceptions\AIAttributeException;
use Parselynk\AiAttributes\Jobs\GenerateAiAttributeJob;
use Parselynk\AiAttributes\Support\PromptCache;
use Parselynk\AiAttributes\Support\PromptFormatter;

use function Laravel\Ai\agent;

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
 *             'prompt'   => 'Write an SEO meta description in under 160 chars',
 *             'persona'  => 'You are an SEO expert.',
 *             'provider' => 'openai',      // any provider from config/ai.php
 *             'model'    => 'gpt-4o',
 *             'timeout'  => 30,
 *         ],
 *     ];
 *
 *     $article->ai_summary;          // string
 *     $article->ai_tags;             // array (auto-decoded)
 *     $article->ai_meta_description; // string from gpt-4o
 *
 * Provider + model + timeout are forwarded to laravel/ai. Persona becomes the
 * agent's system instructions. Everything else (caching, format casting, queues,
 * retries, artisan regenerate) is handled by this package.
 *
 * @property-read array<string, string|array<string, mixed>> $aiAttributes
 */
trait HasAIAttributes
{
    /**
     * Runtime persona override, applied to the next AI attribute read and then cleared.
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
     * Override the persona for the NEXT AI attribute read. Auto-clears after one read.
     */
    public function aiPersona(string $persona): static
    {
        $this->aiPersonaOverride = $persona;

        return $this;
    }

    /**
     * Return the cached value without triggering an AI call. Null if not cached.
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

    public function hasAiAttribute(string $attribute): bool
    {
        $config = $this->resolveAttributeConfig($attribute);

        if ($this->aiPersonaOverride !== null) {
            $config['persona'] = $this->aiPersonaOverride;
        }

        return app(PromptCache::class)->has($this->aiCacheInputs($attribute, $config));
    }

    /**
     * Dispatch generation to Laravel's queue. Model must be persisted so the
     * worker can rehydrate it.
     */
    public function generateAiAttributeAsync(string $attribute): PendingDispatch
    {
        if (! $this->exists) {
            throw AIAttributeException::cannotQueueUnsavedModel(static::class);
        }

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
            fn () => $this->callAgent($config, $context),
        );

        return $this->castFormat($raw, (string) ($config['format'] ?? 'text'));
    }

    /**
     * Delegate the actual AI call to laravel/ai.
     */
    protected function callAgent(array $config, array $context): string
    {
        $persona = (string) ($config['persona'] ?? '');
        $instructions = PromptFormatter::systemMessage($persona !== '' ? $persona : null);
        $message = PromptFormatter::userMessage($config['prompt'], $context);

        $provider = $config['provider']
            ?? config('ai-attributes.default_provider')
            ?? null;

        $response = agent($instructions)->prompt(
            prompt: $message,
            provider: $provider,
            model: $config['model'] ?? null,
            timeout: (int) ($config['timeout'] ?? config('ai-attributes.timeout', 60)),
        );

        return trim((string) $response);
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
            'provider' => $config['provider'] ?? config('ai-attributes.default_provider'),
            'model' => $config['model'] ?? null,
            'format' => $config['format'] ?? 'text',
            'attributes' => $context ?? $this->aiAttributeContext(),
        ];
    }

    protected function aiAttributeContext(): array
    {
        $attributes = $this->attributesToArray();

        if ($this->usesTimestamps()) {
            unset(
                $attributes[$this->getCreatedAtColumn()],
                $attributes[$this->getUpdatedAtColumn()],
            );
        }

        return $attributes;
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

    /**
     * Anonymous agent class used by this trait. Exposed so tests can fake it via
     * `AnonymousAgent::fake([...])` or `Ai::fakeAgent(AnonymousAgent::class, [...])`.
     */
    public static function aiAgentClass(): string
    {
        return AnonymousAgent::class;
    }
}
