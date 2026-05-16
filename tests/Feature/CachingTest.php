<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Parselynk\AiAttributes\Tests\Fixtures\CachedArticle;

beforeEach(function () {
    config()->set('ai-attributes.default', 'claude');
});

it('caches identical inputs so the AI is only called once', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'cached result']],
        ]),
    ]);

    $a = new CachedArticle(['title' => 'Hello']);
    $b = new CachedArticle(['title' => 'Hello']);

    expect($a->ai_summary)->toBe('cached result');
    expect($b->ai_summary)->toBe('cached result');

    Http::assertSentCount(1);
});

it('regenerates when model attributes change', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push(['content' => [['type' => 'text', 'text' => 'first']]])
            ->push(['content' => [['type' => 'text', 'text' => 'second']]]),
    ]);

    $a = new CachedArticle(['title' => 'Hello']);
    $b = new CachedArticle(['title' => 'World']);

    expect($a->ai_summary)->toBe('first');
    expect($b->ai_summary)->toBe('second');

    Http::assertSentCount(2);
});

it('respects the cache.enabled flag', function () {
    config()->set('ai-attributes.cache.enabled', false);

    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push(['content' => [['type' => 'text', 'text' => 'one']]])
            ->push(['content' => [['type' => 'text', 'text' => 'two']]]),
    ]);

    $a = new CachedArticle(['title' => 'Hello']);

    expect($a->ai_summary)->toBe('one');
    expect($a->ai_summary)->toBe('two');

    Http::assertSentCount(2);
});

it('forgetAiAttribute() removes the cached value so the next read regenerates', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push(['content' => [['type' => 'text', 'text' => 'first']]])
            ->push(['content' => [['type' => 'text', 'text' => 'second']]]),
    ]);

    $a = new CachedArticle(['title' => 'Hello']);

    expect($a->ai_summary)->toBe('first');
    expect($a->ai_summary)->toBe('first'); // cache hit

    $a->forgetAiAttribute('summary');

    expect($a->ai_summary)->toBe('second');
    Http::assertSentCount(2);
});
