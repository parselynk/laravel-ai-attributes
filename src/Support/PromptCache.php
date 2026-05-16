<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes\Support;

use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as Config;

class PromptCache
{
    public function __construct(
        protected CacheFactory $cache,
        protected Config $config,
    ) {}

    public function remember(array $inputs, Closure $callback): string
    {
        if (! $this->enabled()) {
            return (string) $callback();
        }

        return (string) $this->store()->remember(
            $this->key($inputs),
            $this->ttl(),
            $callback,
        );
    }

    public function forget(array $inputs): bool
    {
        return $this->store()->forget($this->key($inputs));
    }

    public function get(array $inputs): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $value = $this->store()->get($this->key($inputs));

        return $value === null ? null : (string) $value;
    }

    public function has(array $inputs): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        return $this->store()->has($this->key($inputs));
    }

    public function key(array $inputs): string
    {
        $normalized = $this->normalize($inputs);

        $hash = hash('sha256', (string) json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return sprintf('%s:%s', $this->prefix(), $hash);
    }

    protected function normalize(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->normalize($item);
            }
        }

        return $value;
    }

    protected function enabled(): bool
    {
        return (bool) $this->config->get('ai-attributes.cache.enabled', true);
    }

    protected function store(): CacheRepository
    {
        return $this->cache->store($this->config->get('ai-attributes.cache.store'));
    }

    protected function ttl(): int
    {
        return (int) $this->config->get('ai-attributes.cache.ttl', 60 * 60 * 24 * 30);
    }

    protected function prefix(): string
    {
        return (string) $this->config->get('ai-attributes.cache.prefix', 'ai_attr');
    }
}
