<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;
use Parselynk\AiAttributes\Console\RegenerateAttributesCommand;
use Parselynk\AiAttributes\Support\PromptCache;

class AiAttributesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-attributes.php', 'ai-attributes');

        $this->app->singleton(AIManager::class, fn ($app) => new AIManager($app));

        $this->app->singleton(PromptCache::class, fn ($app) => new PromptCache(
            $app->make(CacheFactory::class),
            $app->make(ConfigRepository::class),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ai-attributes.php' => config_path('ai-attributes.php'),
            ], 'ai-attributes-config');

            $this->commands([
                RegenerateAttributesCommand::class,
            ]);
        }
    }
}
