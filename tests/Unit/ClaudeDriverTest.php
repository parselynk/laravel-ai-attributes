<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Parselynk\AiAttributes\Drivers\ClaudeDriver;
use Parselynk\AiAttributes\Exceptions\AIAttributeException;

function claudeConfig(array $overrides = []): array
{
    return array_merge([
        'api_key' => 'test-key',
        'base_url' => 'https://api.anthropic.com',
        'model' => 'claude-sonnet-4-6',
        'max_tokens' => 256,
        'timeout' => 10,
        'version' => '2023-06-01',
        'retries' => [
            'max_attempts' => 3,
            'base_delay_ms' => 0,
        ],
    ], $overrides);
}

it('sends a request to claude with the configured model and returns the trimmed text', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '  Hello from Claude.  ']],
        ]),
    ]);

    $result = (new ClaudeDriver(claudeConfig()))->generate('Summarize', ['title' => 'Hi']);

    expect($result)->toBe('Hello from Claude.');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.anthropic.com/v1/messages'
        && $request->hasHeader('x-api-key', 'test-key')
        && $request->hasHeader('anthropic-version', '2023-06-01')
        && $request['model'] === 'claude-sonnet-4-6'
        && $request['max_tokens'] === 256);
});

it('sends a system message that asks for clean output', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'ok']],
        ]),
    ]);

    (new ClaudeDriver(claudeConfig()))->generate('Summarize', ['title' => 'Hi']);

    Http::assertSent(function ($request) {
        return is_string($request['system'])
            && str_contains($request['system'], 'AI assistant')
            && str_contains($request['system'], 'no preamble');
    });
});

it('formats the user message with Instruction and Data sections', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'ok']],
        ]),
    ]);

    (new ClaudeDriver(claudeConfig()))->generate('Summarize', ['title' => 'Hello', 'body' => 'world']);

    Http::assertSent(function ($request) {
        $content = $request['messages'][0]['content'];

        return str_contains($content, 'Instruction:')
            && str_contains($content, 'Summarize')
            && str_contains($content, 'Data (JSON):')
            && str_contains($content, '"title": "Hello"')
            && str_contains($content, '"body": "world"');
    });
});

it('omits the data section when context is empty', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'ok']],
        ]),
    ]);

    (new ClaudeDriver(claudeConfig()))->generate('Tell me a joke');

    Http::assertSent(function ($request) {
        $content = $request['messages'][0]['content'];

        return $content === 'Tell me a joke';
    });
});

it('throws when the api key is missing', function () {
    (new ClaudeDriver(claudeConfig(['api_key' => null])))->generate('hi');
})->throws(AIAttributeException::class, 'Missing API key for AI driver [claude]');

it('throws when the provider returns a non-2xx response', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response('boom', 500),
    ]);

    (new ClaudeDriver(claudeConfig()))->generate('hi');
})->throws(AIAttributeException::class, 'returned HTTP 500');

it('throws when the response shape is unexpected', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(['unexpected' => 'shape']),
    ]);

    (new ClaudeDriver(claudeConfig()))->generate('hi');
})->throws(AIAttributeException::class, 'malformed response');
