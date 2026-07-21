<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes;

use Illuminate\Support\Manager;
use Parselynk\AiAttributes\Drivers\ClaudeDriver;
use Parselynk\AiAttributes\Drivers\OllamaDriver;
use Parselynk\AiAttributes\Drivers\OpenAIDriver;

class AIManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('ai-attributes.default', 'claude');
    }

    protected function createClaudeDriver(): ClaudeDriver
    {
        return new ClaudeDriver($this->driverConfig('claude'));
    }

    protected function createOpenaiDriver(): OpenAIDriver
    {
        return new OpenAIDriver($this->driverConfig('openai'));
    }

    protected function createOllamaDriver(): OllamaDriver
    {
        return new OllamaDriver($this->driverConfig('ollama'));
    }

    protected function driverConfig(string $name): array
    {
        return (array) $this->config->get("ai-attributes.drivers.{$name}", []);
    }
}
