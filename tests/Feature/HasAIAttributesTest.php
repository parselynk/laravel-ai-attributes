<?php

declare(strict_types=1);

use Laravel\Ai\AnonymousAgent;
use Parselynk\AiAttributes\Exceptions\AIAttributeException;
use Parselynk\AiAttributes\Tests\Fixtures\TestArticle;

it('exposes ai_* accessors that hit laravel/ai', function () {
    AnonymousAgent::fake(['A two-sentence summary.']);

    $article = new TestArticle(['title' => 'Hello', 'body' => 'world']);

    expect($article->ai_summary)->toBe('A two-sentence summary.');
});

it('passes the prompt AND the model attributes to the agent', function () {
    AnonymousAgent::fake(function ($prompt) {
        expect($prompt)->toContain('Summarize this in 2 sentences');
        expect($prompt)->toContain('"title": "Hello"');
        expect($prompt)->toContain('"body": "world"');

        return 'ok';
    });

    $article = new TestArticle(['title' => 'Hello', 'body' => 'world']);
    $article->ai_summary;
});

it('does not intercept non-ai attributes', function () {
    $article = new TestArticle(['title' => 'Hello']);

    expect($article->title)->toBe('Hello');
});

it('returns null for ai_* keys not declared in $aiAttributes', function () {
    $article = new TestArticle(['title' => 'Hello']);

    expect($article->ai_does_not_exist)->toBeNull();
});

it('exposes generateAiAttribute() as an explicit accessor', function () {
    AnonymousAgent::fake(['explicit']);

    $article = new TestArticle(['title' => 'Hello']);

    expect($article->generateAiAttribute('summary'))->toBe('explicit');
});

it('throws when generateAiAttribute() is called for an undeclared attribute', function () {
    $article = new TestArticle(['title' => 'Hello']);

    $article->generateAiAttribute('nope');
})->throws(AIAttributeException::class, 'No AI attribute defined for [nope]');

it('trims whitespace from the AI response', function () {
    AnonymousAgent::fake(['   padded response   ']);

    $article = new TestArticle(['title' => 'Hello']);

    expect($article->ai_summary)->toBe('padded response');
});
