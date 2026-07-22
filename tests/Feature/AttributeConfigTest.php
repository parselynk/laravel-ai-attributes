<?php

declare(strict_types=1);

use Laravel\Ai\AnonymousAgent;
use Parselynk\AiAttributes\Tests\Fixtures\RichArticle;

it('still supports the simple string-prompt form (backwards compat)', function () {
    AnonymousAgent::fake(['A summary.']);

    $article = new RichArticle(['title' => 'Hi', 'body' => 'world']);

    expect($article->ai_summary)->toBe('A summary.');
});

it('sends the configured persona as part of the agent instructions', function () {
    AnonymousAgent::fake(['Meta desc.']);

    $article = new RichArticle(['title' => 'Hi', 'body' => 'world']);
    $article->ai_meta_description;

    AnonymousAgent::assertPrompted(fn ($p) => str_contains($p->agent->instructions(), 'You are an SEO expert'));
});

it('respects a per-attribute provider override', function () {
    AnonymousAgent::fake(['Bonjour le monde.']);

    $article = new RichArticle(['body' => 'Hello world']);

    expect($article->ai_translated)->toBe('Bonjour le monde.');

    AnonymousAgent::assertPrompted(fn ($p) => $p->provider->name() === 'openai');
});

it('respects a per-attribute model override', function () {
    AnonymousAgent::fake(['ok']);

    $article = new RichArticle(['body' => 'Hello']);
    $article->ai_translated;

    AnonymousAgent::assertPrompted(fn ($p) => $p->model === 'gpt-4o');
});

it('decodes a JSON response into an array when format=json', function () {
    AnonymousAgent::fake(['["laravel", "php", "release"]']);

    $article = new RichArticle(['title' => 'Hi']);

    expect($article->ai_tags)
        ->toBeArray()
        ->toHaveCount(3)
        ->toContain('laravel');
});

it('strips markdown fences from JSON responses', function () {
    AnonymousAgent::fake(["```json\n[\"a\", \"b\"]\n```"]);

    $article = new RichArticle(['title' => 'Hi']);

    expect($article->ai_tags)->toBe(['a', 'b']);
});

it('throws when JSON format gets invalid JSON back', function () {
    AnonymousAgent::fake(['not json at all']);

    $article = new RichArticle(['title' => 'Hi']);
    $article->ai_tags;
})->throws(\Parselynk\AiAttributes\Exceptions\AIAttributeException::class, 'invalid JSON');

it('casts a numeric response to int when format=number', function () {
    AnonymousAgent::fake(['42']);

    $article = new RichArticle(['title' => 'Hi']);

    expect($article->ai_sentiment)->toBe(42);
});

it('casts a decimal response to float when format=number', function () {
    AnonymousAgent::fake(['-12.5']);

    $article = new RichArticle(['title' => 'Hi']);

    expect($article->ai_sentiment)->toBe(-12.5);
});

it('throws when format=number gets non-numeric back', function () {
    AnonymousAgent::fake(['kinda positive']);

    $article = new RichArticle(['title' => 'Hi']);
    $article->ai_sentiment;
})->throws(\Parselynk\AiAttributes\Exceptions\AIAttributeException::class, 'non-numeric');

it('casts yes/no responses to bool when format=bool', function () {
    AnonymousAgent::fake(['yes', 'NO']);

    $a = new RichArticle(['title' => 'Click here to find out!']);
    $b = new RichArticle(['title' => 'Q3 earnings report']);

    expect($a->ai_is_clickbait)->toBeTrue();
    expect($b->ai_is_clickbait)->toBeFalse();
});

it('throws when format=bool gets unrecognized text back', function () {
    AnonymousAgent::fake(['maybe']);

    $article = new RichArticle(['title' => 'Hi']);
    $article->ai_is_clickbait;
})->throws(\Parselynk\AiAttributes\Exceptions\AIAttributeException::class, 'cast to bool');

it('regenerates when persona changes (cache key includes persona)', function () {
    $calls = 0;
    AnonymousAgent::fake(function () use (&$calls) {
        $calls++;

        return 'ok';
    });

    $article = new RichArticle(['title' => 'Hi']);
    $article->ai_summary;          // no persona
    $article->ai_meta_description; // has SEO persona

    expect($calls)->toBe(2);
});
