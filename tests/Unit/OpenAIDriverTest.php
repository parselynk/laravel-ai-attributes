<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Parselynk\AiAttributes\Drivers\OpenAIDriver;
use Parselynk\AiAttributes\Exceptions\AIAttributeException;

function openaiConfig(array $overrides = []): array
{
    return array_merge([
        'api_key' => 'sk-test',
        'base_url' => 'https://api.openai.com/v1',
        'model' => 'gpt-4o-mini',
        'max_tokens' => 256,
        'timeout' => 10,
        'retries' => [
            'max_attempts' => 3,
            'base_delay_ms' => 0,
        ],
    ], $overrides);
}

it('sends a chat completion request and returns the assistant message', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => 'Hello from GPT.']],
            ],
        ]),
    ]);

    $result = (new OpenAIDriver(openaiConfig()))->generate('Translate to French', ['text' => 'hello']);

    expect($result)->toBe('Hello from GPT.');

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.openai.com/v1/chat/completions')
        && $request->hasHeader('Authorization', 'Bearer sk-test')
        && $request['model'] === 'gpt-4o-mini');
});

it('sends a system message as the first message in the array', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'ok']]],
        ]),
    ]);

    (new OpenAIDriver(openaiConfig()))->generate('Summarize', ['title' => 'Hi']);

    Http::assertSent(function ($request) {
        $messages = $request['messages'];

        return count($messages) === 2
            && $messages[0]['role'] === 'system'
            && str_contains($messages[0]['content'], 'AI assistant')
            && $messages[1]['role'] === 'user'
            && str_contains($messages[1]['content'], 'Instruction:')
            && str_contains($messages[1]['content'], '"title": "Hi"');
    });
});

it('throws when the api key is missing', function () {
    (new OpenAIDriver(openaiConfig(['api_key' => ''])))->generate('hi');
})->throws(AIAttributeException::class, 'Missing API key for AI driver [openai]');

it('throws when the provider returns a non-2xx response', function () {
    Http::fake([
        'api.openai.com/*' => Http::response('rate limited', 429),
    ]);

    (new OpenAIDriver(openaiConfig()))->generate('hi');
})->throws(AIAttributeException::class, 'returned HTTP 429');
