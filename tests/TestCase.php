<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes\Tests;

use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Parselynk\AiAttributes\AiAttributesServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            AiAttributesServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');

        // Give laravel/ai a bare-minimum config so `agent()` calls don't error
        // when tests haven't specified a provider. Everything is faked anyway.
        $app['config']->set('ai.default', 'openai');
        $app['config']->set('ai.providers.openai', [
            'driver' => 'openai',
            'key' => 'sk-test',
            'url' => 'https://api.openai.com/v1',
        ]);
        $app['config']->set('ai.providers.anthropic', [
            'driver' => 'anthropic',
            'key' => 'test-anthropic-key',
            'url' => 'https://api.anthropic.com/v1',
        ]);
        $app['config']->set('ai.providers.ollama', [
            'driver' => 'ollama',
            'url' => 'http://localhost:11434',
            'key' => null,
        ]);
        $app['config']->set('ai.models.text.default', 'gpt-4o-mini');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/migrations');
    }
}
