<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Parselynk\AiAttributes\AiAttributesServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [AiAttributesServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('ai-attributes.drivers.claude.api_key', 'test-claude-key');
        $app['config']->set('ai-attributes.drivers.openai.api_key', 'test-openai-key');
        $app['config']->set('ai-attributes.drivers.claude.retries.base_delay_ms', 0);
        $app['config']->set('ai-attributes.drivers.openai.retries.base_delay_ms', 0);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/migrations');
    }
}
