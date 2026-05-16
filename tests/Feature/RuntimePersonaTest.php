<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Parselynk\AiAttributes\Tests\Fixtures\PersonaDemo;
use Parselynk\AiAttributes\Tests\Fixtures\RichArticle;

beforeEach(function () {
    config()->set('ai-attributes.default', 'claude');
});

it('aiPersona() returns the model for fluent chaining', function () {
    $article = new RichArticle(['title' => 'Hi']);

    expect($article->aiPersona('You are a poet'))->toBe($article);
});

it('uses the runtime persona instead of the declared persona for the next read', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'verily, hot pizza']],
        ]),
    ]);

    $article = new RichArticle(['title' => 'Pizza']);
    $article->aiPersona('You are Shakespeare.')->ai_meta_description;

    Http::assertSent(function ($request) {
        return str_contains($request['system'], 'Shakespeare')
            && ! str_contains($request['system'], 'SEO expert');
    });
});

it('overrides even attributes without a declared persona', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'arrr']],
        ]),
    ]);

    $article = new RichArticle(['title' => 'Pizza', 'body' => 'hot']);
    $article->aiPersona('You are a pirate.')->ai_summary;

    Http::assertSent(fn ($request) => str_contains($request['system'], 'pirate'));
});

it('clears the override after one read so the next read uses the declared persona', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push(['content' => [['type' => 'text', 'text' => 'arrr meta']]])
            ->push(['content' => [['type' => 'text', 'text' => 'normal meta']]]),
    ]);

    $article = new RichArticle(['title' => 'Pizza']);

    $article->aiPersona('You are a pirate.')->ai_meta_description;
    $article->ai_meta_description;  // should use declared SEO persona again

    $requests = Http::recorded();

    expect($requests[0][0]['system'])->toContain('pirate');
    expect($requests[1][0]['system'])->toContain('SEO expert');
    expect($requests[1][0]['system'])->not->toContain('pirate');
});

it('different runtime personas produce different cache entries', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push(['content' => [['type' => 'text', 'text' => 'poet output']]])
            ->push(['content' => [['type' => 'text', 'text' => 'pirate output']]]),
    ]);

    $a = new PersonaDemo(['subject' => 'a hot pizza']);
    $b = new PersonaDemo(['subject' => 'a hot pizza']);

    $a->aiPersona('You are a poet.')->ai_plain;
    $b->aiPersona('You are a pirate.')->ai_plain;

    Http::assertSentCount(2);
});

it('same runtime persona reuses the cache', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'cached poet']],
        ]),
    ]);

    $a = new PersonaDemo(['subject' => 'a hot pizza']);
    $b = new PersonaDemo(['subject' => 'a hot pizza']);

    $a->aiPersona('You are a poet.')->ai_plain;
    $b->aiPersona('You are a poet.')->ai_plain;

    Http::assertSentCount(1);
});

it('a second aiPersona() call replaces the previous override before the read', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'ok']],
        ]),
    ]);

    $article = new RichArticle(['title' => 'Pizza']);
    $article->aiPersona('first persona')->aiPersona('second persona')->ai_summary;

    Http::assertSent(fn ($request) => str_contains($request['system'], 'second persona')
        && ! str_contains($request['system'], 'first persona'));
});
