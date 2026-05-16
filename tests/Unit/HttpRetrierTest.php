<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Parselynk\AiAttributes\Drivers\ClaudeDriver;
use Parselynk\AiAttributes\Drivers\OpenAIDriver;
use Parselynk\AiAttributes\Exceptions\AIAttributeException;
use Parselynk\AiAttributes\Support\HttpRetrier;

it('returns immediately on a successful response (no retries)', function () {
    $calls = 0;

    $response = HttpRetrier::execute(function () use (&$calls) {
        $calls++;
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        return Http::get('https://example.test');
    }, maxAttempts: 3, baseDelayMs: 0);

    expect($calls)->toBe(1);
    expect($response->status())->toBe(200);
});

it('retries on HTTP 429 then succeeds', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push(['error' => 'rate limit'], 429)
            ->push(['content' => [['type' => 'text', 'text' => 'success']]], 200),
    ]);

    $result = (new ClaudeDriver(claudeConfig()))->generate('hi');

    expect($result)->toBe('success');
    Http::assertSentCount(2);
});

it('retries on HTTP 500 then succeeds', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push('boom', 500)
            ->push('boom again', 503)
            ->push(['content' => [['type' => 'text', 'text' => 'recovered']]], 200),
    ]);

    $result = (new ClaudeDriver(claudeConfig()))->generate('hi');

    expect($result)->toBe('recovered');
    Http::assertSentCount(3);
});

it('gives up after max_attempts and throws', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response('still down', 503),
    ]);

    (new ClaudeDriver(claudeConfig()))->generate('hi');
})->throws(AIAttributeException::class, 'returned HTTP 503');

it('counts exactly max_attempts requests when all fail', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response('boom', 500),
    ]);

    try {
        (new ClaudeDriver(claudeConfig()))->generate('hi');
    } catch (AIAttributeException) {
        // expected
    }

    Http::assertSentCount(3);
});

it('does NOT retry on 401 (auth failure — caller cannot fix by retrying)', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(['error' => 'invalid api key'], 401),
    ]);

    try {
        (new ClaudeDriver(claudeConfig()))->generate('hi');
    } catch (AIAttributeException) {
        // expected
    }

    Http::assertSentCount(1);
});

it('does NOT retry on 400 (bad request)', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(['error' => 'bad request'], 400),
    ]);

    try {
        (new ClaudeDriver(claudeConfig()))->generate('hi');
    } catch (AIAttributeException) {
        // expected
    }

    Http::assertSentCount(1);
});

it('does NOT retry on 402 (billing failure)', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(['error' => 'payment required'], 402),
    ]);

    try {
        (new ClaudeDriver(claudeConfig()))->generate('hi');
    } catch (AIAttributeException) {
        // expected
    }

    Http::assertSentCount(1);
});

it('also applies retry behavior to the OpenAI driver', function () {
    Http::fake([
        'api.openai.com/*' => Http::sequence()
            ->push(['error' => 'rate limit'], 429)
            ->push(['choices' => [['message' => ['content' => 'recovered']]]], 200),
    ]);

    $result = (new OpenAIDriver(openaiConfig()))->generate('hi');

    expect($result)->toBe('recovered');
    Http::assertSentCount(2);
});

it('retries on connection exceptions', function () {
    $attempts = 0;

    $response = HttpRetrier::execute(function () use (&$attempts) {
        $attempts++;

        if ($attempts < 3) {
            throw new ConnectionException('network broken');
        }

        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        return Http::get('https://example.test');
    }, maxAttempts: 3, baseDelayMs: 0);

    expect($attempts)->toBe(3);
    expect($response->status())->toBe(200);
});

it('rethrows the connection exception when retries are exhausted', function () {
    HttpRetrier::execute(function () {
        throw new ConnectionException('still broken');
    }, maxAttempts: 2, baseDelayMs: 0);
})->throws(ConnectionException::class, 'still broken');
