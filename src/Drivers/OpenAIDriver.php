<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes\Drivers;

use Illuminate\Support\Facades\Http;
use Parselynk\AiAttributes\Contracts\AIDriver;
use Parselynk\AiAttributes\Exceptions\AIAttributeException;
use Parselynk\AiAttributes\Support\HttpRetrier;
use Parselynk\AiAttributes\Support\PromptFormatter;

class OpenAIDriver implements AIDriver
{
    public function __construct(protected array $config) {}

    public function generate(string $prompt, array $context = [], array $options = []): string
    {
        $apiKey = $this->config['api_key'] ?? null;

        if (empty($apiKey)) {
            throw AIAttributeException::missingApiKey('openai');
        }

        $response = HttpRetrier::execute(
            fn () => Http::withToken($apiKey)
                ->timeout($this->config['timeout'] ?? 30)
                ->post(rtrim($this->config['base_url'], '/').'/chat/completions', [
                    'model' => $options['model'] ?? $this->config['model'],
                    'max_tokens' => $options['max_tokens'] ?? $this->config['max_tokens'],
                    'messages' => [
                        ['role' => 'system', 'content' => PromptFormatter::systemMessage($options['persona'] ?? null)],
                        ['role' => 'user', 'content' => PromptFormatter::userMessage($prompt, $context)],
                    ],
                ]),
            $this->config['retries']['max_attempts'] ?? 3,
            $this->config['retries']['base_delay_ms'] ?? 1000,
        );

        if ($response->failed()) {
            throw AIAttributeException::providerFailed('openai', $response->status(), $response->body());
        }

        $payload = $response->json();
        $text = $payload['choices'][0]['message']['content'] ?? null;

        if (! is_string($text)) {
            throw AIAttributeException::malformedResponse('openai', $payload);
        }

        return trim($text);
    }
}
