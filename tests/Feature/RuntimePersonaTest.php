<?php

declare(strict_types=1);

use Laravel\Ai\AnonymousAgent;
use Parselynk\AiAttributes\Tests\Fixtures\PersonaDemo;
use Parselynk\AiAttributes\Tests\Fixtures\RichArticle;

it('aiPersona() returns the model for fluent chaining', function () {
    $article = new RichArticle(['title' => 'Hi']);

    expect($article->aiPersona('You are a poet'))->toBe($article);
});

it('uses the runtime persona instead of the declared persona for the next read', function () {
    AnonymousAgent::fake(['verily, hot pizza']);

    $article = new RichArticle(['title' => 'Pizza']);
    $article->aiPersona('You are Shakespeare.')->ai_meta_description;

    AnonymousAgent::assertPrompted(fn ($p) => str_contains($p->agent->instructions(), 'Shakespeare')
        && ! str_contains($p->agent->instructions(), 'SEO expert'));
});

it('overrides even attributes without a declared persona', function () {
    AnonymousAgent::fake(['arrr']);

    $article = new RichArticle(['title' => 'Pizza', 'body' => 'hot']);
    $article->aiPersona('You are a pirate.')->ai_summary;

    AnonymousAgent::assertPrompted(fn ($p) => str_contains($p->agent->instructions(), 'pirate'));
});

it('clears the override after one read so the next read uses the declared persona', function () {
    AnonymousAgent::fake(['arrr meta', 'normal meta']);

    $article = new RichArticle(['title' => 'Pizza']);

    $article->aiPersona('You are a pirate.')->ai_meta_description;
    $article->ai_meta_description;  // should use declared SEO persona again

    // Both prompts should have been recorded — first with pirate, second with SEO expert.
    AnonymousAgent::assertPrompted(fn ($p) => str_contains($p->agent->instructions(), 'pirate'));
    AnonymousAgent::assertPrompted(fn ($p) => str_contains($p->agent->instructions(), 'SEO expert')
        && ! str_contains($p->agent->instructions(), 'pirate'));
});

it('different runtime personas produce different cache entries', function () {
    $calls = 0;
    AnonymousAgent::fake(function () use (&$calls) {
        $calls++;

        return "output {$calls}";
    });

    $a = new PersonaDemo(['subject' => 'a hot pizza']);
    $b = new PersonaDemo(['subject' => 'a hot pizza']);

    $a->aiPersona('You are a poet.')->ai_plain;
    $b->aiPersona('You are a pirate.')->ai_plain;

    expect($calls)->toBe(2);
});

it('same runtime persona reuses the cache', function () {
    $calls = 0;
    AnonymousAgent::fake(function () use (&$calls) {
        $calls++;

        return 'cached poet';
    });

    $a = new PersonaDemo(['subject' => 'a hot pizza']);
    $b = new PersonaDemo(['subject' => 'a hot pizza']);

    $a->aiPersona('You are a poet.')->ai_plain;
    $b->aiPersona('You are a poet.')->ai_plain;

    expect($calls)->toBe(1);
});

it('a second aiPersona() call replaces the previous override before the read', function () {
    AnonymousAgent::fake(['ok']);

    $article = new RichArticle(['title' => 'Pizza']);
    $article->aiPersona('first persona')->aiPersona('second persona')->ai_summary;

    AnonymousAgent::assertPrompted(fn ($p) => str_contains($p->agent->instructions(), 'second persona')
        && ! str_contains($p->agent->instructions(), 'first persona'));
});
