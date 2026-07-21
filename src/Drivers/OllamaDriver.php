<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes\Drivers;

use Illuminate\Support\Facades\Http;
use Parselynk\AiAttributes\Contracts\AIDriver;
use Parselynk\AiAttributes\Exceptions\AIAttributeException;
use Parselynk\AiAttributes\Support\HttpRetrier;
use Parselynk\AiAttributes\Support\PromptFormatter;

/**
 * Driver for a local (or remote) Ollama server.
 *
 * Ollama exposes a chat endpoint at /api/chat that is shaped similarly to
 * OpenAI's chat completions but with a different response envelope and no auth.
 *
 *   POST {base_url}/api/chat
 *   {
 *     "model": "...",
 *     "stream": false,
 *     "messages": [
 *       { "role": "system", "content": "..." },
 *       { "role": "user",   "content": "..." }
 *     ]
 *   }
 *
 * Response → message.content holds the assistant's reply.
 *
 * Default base_url is http://localhost:11434 (Ollama's standard port).
 * Override base_url in config to point at a remote Ollama (Docker, GPU box, etc).
 */
class OllamaDriver implements AIDriver
{
    public function __construct(protected array $config) {}

    public function generate(string $prompt, array $context = [], array $options = []): string
    {
        $body = [
            'model' => $options['model'] ?? $this->config['model'],
            'stream' => false,
            'messages' => [
                ['role' => 'system', 'content' => PromptFormatter::systemMessage($options['persona'] ?? null)],
                ['role' => 'user', 'content' => PromptFormatter::userMessage($prompt, $context)],
            ],
        ];

        // Ollama accepts inference parameters under `options`.
        // Temperature defaults to 0 for predictable, structured output (best for JSON / number / bool casts).
        // Override per-attribute via $options['temperature'] or globally via config.
        $temperature = $options['temperature']
            ?? $this->config['temperature']
            ?? 0;

        $body['options'] = ['temperature' => (float) $temperature];

        $response = HttpRetrier::execute(
            fn () => Http::timeout($this->config['timeout'] ?? 60)
                ->post(rtrim($this->config['base_url'], '/').'/api/chat', $body),
            $this->config['retries']['max_attempts'] ?? 3,
            $this->config['retries']['base_delay_ms'] ?? 1000,
        );

        if ($response->failed()) {
            throw AIAttributeException::providerFailed('ollama', $response->status(), $response->body());
        }

        $payload = $response->json();
        $text = $payload['message']['content'] ?? null;

        if (! is_string($text)) {
            throw AIAttributeException::malformedResponse('ollama', $payload);
        }

        return trim($text);
    }
}
