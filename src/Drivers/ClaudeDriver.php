<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes\Drivers;

use Illuminate\Support\Facades\Http;
use Parselynk\AiAttributes\Contracts\AIDriver;
use Parselynk\AiAttributes\Exceptions\AIAttributeException;
use Parselynk\AiAttributes\Support\HttpRetrier;
use Parselynk\AiAttributes\Support\PromptFormatter;

class ClaudeDriver implements AIDriver
{
    public function __construct(protected array $config) {}

    public function generate(string $prompt, array $context = [], array $options = []): string
    {
        $apiKey = $this->config['api_key'] ?? null;

        if (empty($apiKey)) {
            throw AIAttributeException::missingApiKey('claude');
        }

        $response = HttpRetrier::execute(
            fn () => Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => $this->config['version'] ?? '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->timeout($this->config['timeout'] ?? 30)
                ->post(rtrim($this->config['base_url'], '/').'/v1/messages', [
                    'model' => $options['model'] ?? $this->config['model'],
                    'max_tokens' => $options['max_tokens'] ?? $this->config['max_tokens'],
                    'system' => PromptFormatter::systemMessage($options['persona'] ?? null),
                    'messages' => [
                        ['role' => 'user', 'content' => PromptFormatter::userMessage($prompt, $context)],
                    ],
                ]),
            $this->config['retries']['max_attempts'] ?? 3,
            $this->config['retries']['base_delay_ms'] ?? 1000,
        );

        if ($response->failed()) {
            throw AIAttributeException::providerFailed('claude', $response->status(), $response->body());
        }

        $payload = $response->json();
        $text = $payload['content'][0]['text'] ?? null;

        if (! is_string($text)) {
            throw AIAttributeException::malformedResponse('claude', $payload);
        }

        return trim($text);
    }
}
