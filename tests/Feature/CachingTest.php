<?php

declare(strict_types=1);

use Laravel\Ai\AnonymousAgent;
use Parselynk\AiAttributes\Tests\Fixtures\CachedArticle;

it('caches identical inputs so the AI is only called once', function () {
    $calls = 0;

    AnonymousAgent::fake(function () use (&$calls) {
        $calls++;

        return 'cached result';
    });

    $a = new CachedArticle(['title' => 'Hello']);
    $b = new CachedArticle(['title' => 'Hello']);

    expect($a->ai_summary)->toBe('cached result');
    expect($b->ai_summary)->toBe('cached result');

    expect($calls)->toBe(1);
});

it('regenerates when model attributes change', function () {
    AnonymousAgent::fake(['first', 'second']);

    $a = new CachedArticle(['title' => 'Hello']);
    $b = new CachedArticle(['title' => 'World']);

    expect($a->ai_summary)->toBe('first');
    expect($b->ai_summary)->toBe('second');
});

it('respects the cache.enabled flag', function () {
    config()->set('ai-attributes.cache.enabled', false);

    AnonymousAgent::fake(['one', 'two']);

    $a = new CachedArticle(['title' => 'Hello']);

    expect($a->ai_summary)->toBe('one');
    expect($a->ai_summary)->toBe('two');
});

it('forgetAiAttribute() removes the cached value so the next read regenerates', function () {
    AnonymousAgent::fake(['first', 'second']);

    $a = new CachedArticle(['title' => 'Hello']);

    expect($a->ai_summary)->toBe('first');
    expect($a->ai_summary)->toBe('first'); // cache hit

    $a->forgetAiAttribute('summary');

    expect($a->ai_summary)->toBe('second');
});
