<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Parselynk\AiAttributes\Drivers\OllamaDriver;
use Parselynk\AiAttributes\Exceptions\AIAttributeException;

function ollamaConfig(array $overrides = []): array
{
    return array_merge([
        'base_url' => 'http://localhost:11434',
        'model' => 'llama3.2:3b',
        'timeout' => 30,
        'retries' => [
            'max_attempts' => 2,
            'base_delay_ms' => 0,
        ],
    ], $overrides);
}

it('sends a chat request to the configured Ollama base URL and returns the trimmed text', function () {
    Http::fake([
        'localhost:11434/*' => Http::response([
            'message' => ['role' => 'assistant', 'content' => '  Hello from Ollama.  '],
        ]),
    ]);

    $result = (new OllamaDriver(ollamaConfig()))->generate('Say hi', ['title' => 'demo']);

    expect($result)->toBe('Hello from Ollama.');

    Http::assertSent(fn ($request) => $request->url() === 'http://localhost:11434/api/chat'
        && $request['model'] === 'llama3.2:3b'
        && $request['stream'] === false);
});

it('always sets stream to false so the response is a single JSON object', function () {
    Http::fake([
        'localhost:11434/*' => Http::response([
            'message' => ['content' => 'ok'],
        ]),
    ]);

    (new OllamaDriver(ollamaConfig()))->generate('hi');

    Http::assertSent(fn ($request) => $request['stream'] === false);
});

it('sends NO Authorization header (Ollama is keyless)', function () {
    Http::fake([
        'localhost:11434/*' => Http::response([
            'message' => ['content' => 'ok'],
        ]),
    ]);

    (new OllamaDriver(ollamaConfig()))->generate('hi');

    Http::assertSent(fn ($request) => ! $request->hasHeader('Authorization')
        && ! $request->hasHeader('x-api-key'));
});

it('sends system + user messages in the same shape as OpenAI', function () {
    Http::fake([
        'localhost:11434/*' => Http::response([
            'message' => ['content' => 'ok'],
        ]),
    ]);

    (new OllamaDriver(ollamaConfig()))->generate('Summarize', ['title' => 'Hi']);

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

it('respects a per-call model override', function () {
    Http::fake([
        'localhost:11434/*' => Http::response([
            'message' => ['content' => 'ok'],
        ]),
    ]);

    (new OllamaDriver(ollamaConfig()))->generate('hi', [], ['model' => 'qwen2.5-coder:7b']);

    Http::assertSent(fn ($request) => $request['model'] === 'qwen2.5-coder:7b');
});

it('respects a per-call persona override in the system message', function () {
    Http::fake([
        'localhost:11434/*' => Http::response([
            'message' => ['content' => 'ok'],
        ]),
    ]);

    (new OllamaDriver(ollamaConfig()))->generate('hi', [], ['persona' => 'You are a pirate.']);

    Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'], 'You are a pirate.'));
});

it('honours a custom base_url so users can point at a remote Ollama server', function () {
    Http::fake([
        'ollama.example.com:11434/*' => Http::response([
            'message' => ['content' => 'remote ok'],
        ]),
    ]);

    $result = (new OllamaDriver(ollamaConfig([
        'base_url' => 'http://ollama.example.com:11434',
    ])))->generate('hi');

    expect($result)->toBe('remote ok');

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'http://ollama.example.com:11434/api/chat'));
});

it('throws when the response shape is unexpected (no message.content)', function () {
    Http::fake([
        'localhost:11434/*' => Http::response(['unexpected' => 'shape']),
    ]);

    (new OllamaDriver(ollamaConfig()))->generate('hi');
})->throws(AIAttributeException::class, 'malformed response');

it('throws when Ollama returns a non-2xx response', function () {
    Http::fake([
        'localhost:11434/*' => Http::response(['error' => 'model not found'], 404),
    ]);

    (new OllamaDriver(ollamaConfig()))->generate('hi');
})->throws(AIAttributeException::class, 'returned HTTP 404');

it('sends temperature 0 by default for predictable structured output', function () {
    Http::fake([
        'localhost:11434/*' => Http::response([
            'message' => ['content' => 'ok'],
        ]),
    ]);

    (new OllamaDriver(ollamaConfig()))->generate('hi');

    Http::assertSent(fn ($request) => isset($request['options']['temperature'])
        && $request['options']['temperature'] === 0.0);
});

it('respects a config-level temperature override', function () {
    Http::fake([
        'localhost:11434/*' => Http::response([
            'message' => ['content' => 'ok'],
        ]),
    ]);

    (new OllamaDriver(ollamaConfig(['temperature' => 0.7])))->generate('hi');

    Http::assertSent(fn ($request) => $request['options']['temperature'] === 0.7);
});

it('respects a per-call temperature override (highest priority)', function () {
    Http::fake([
        'localhost:11434/*' => Http::response([
            'message' => ['content' => 'ok'],
        ]),
    ]);

    (new OllamaDriver(ollamaConfig(['temperature' => 0.7])))
        ->generate('hi', [], ['temperature' => 1.2]);

    Http::assertSent(fn ($request) => $request['options']['temperature'] === 1.2);
});

it('retries on 5xx errors (transient local Ollama hiccups)', function () {
    Http::fake([
        'localhost:11434/*' => Http::sequence()
            ->push('boom', 500)
            ->push(['message' => ['content' => 'recovered']], 200),
    ]);

    $result = (new OllamaDriver(ollamaConfig()))->generate('hi');

    expect($result)->toBe('recovered');
    Http::assertSentCount(2);
});
