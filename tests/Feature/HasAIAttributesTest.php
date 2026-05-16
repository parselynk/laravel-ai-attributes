<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Parselynk\AiAttributes\Exceptions\AIAttributeException;
use Parselynk\AiAttributes\Tests\Fixtures\TestArticle;

beforeEach(function () {
    config()->set('ai-attributes.default', 'claude');
});

it('exposes ai_* accessors that hit the configured driver', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'A two-sentence summary.']],
        ]),
    ]);

    $article = new TestArticle(['title' => 'Hello', 'body' => 'world']);

    expect($article->ai_summary)->toBe('A two-sentence summary.');
});

it('passes model attributes as context to the driver', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'ok']],
        ]),
    ]);

    $article = new TestArticle(['title' => 'Hello', 'body' => 'world']);
    $article->ai_summary;

    Http::assertSent(function ($request) {
        $content = $request['messages'][0]['content'];

        return str_contains($content, '"title": "Hello"')
            && str_contains($content, '"body": "world"');
    });
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
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'explicit']],
        ]),
    ]);

    $article = new TestArticle(['title' => 'Hello']);

    expect($article->generateAiAttribute('summary'))->toBe('explicit');
});

it('throws when generateAiAttribute() is called for an undeclared attribute', function () {
    $article = new TestArticle(['title' => 'Hello']);

    $article->generateAiAttribute('nope');
})->throws(AIAttributeException::class, 'No AI attribute defined for [nope]');
