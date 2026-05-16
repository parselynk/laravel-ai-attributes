<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Parselynk\AiAttributes\Tests\Fixtures\RichArticle;

beforeEach(function () {
    config()->set('ai-attributes.default', 'claude');
});

it('still supports the simple string-prompt form (backwards compat)', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'A summary.']],
        ]),
    ]);

    $article = new RichArticle(['title' => 'Hi', 'body' => 'world']);

    expect($article->ai_summary)->toBe('A summary.');
});

it('sends the configured persona as the system message', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Meta desc.']],
        ]),
    ]);

    $article = new RichArticle(['title' => 'Hi', 'body' => 'world']);
    $article->ai_meta_description;

    Http::assertSent(function ($request) {
        return str_contains($request['system'], 'You are an SEO expert');
    });
});

it('respects per-attribute driver override (uses openai instead of claude)', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Bonjour le monde.']]],
        ]),
    ]);

    $article = new RichArticle(['body' => 'Hello world']);

    expect($article->ai_translated)->toBe('Bonjour le monde.');

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.openai.com/v1/chat/completions'));
});

it('respects per-attribute model and max_tokens overrides', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'ok']]],
        ]),
    ]);

    $article = new RichArticle(['body' => 'Hello']);
    $article->ai_translated;

    Http::assertSent(fn ($request) => $request['model'] === 'gpt-4o'
        && $request['max_tokens'] === 500);
});

it('decodes a JSON response into an array when format=json', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '["laravel", "php", "release"]']],
        ]),
    ]);

    $article = new RichArticle(['title' => 'Hi']);

    expect($article->ai_tags)
        ->toBeArray()
        ->toHaveCount(3)
        ->toContain('laravel');
});

it('strips markdown fences from JSON responses', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => "```json\n[\"a\", \"b\"]\n```"]],
        ]),
    ]);

    $article = new RichArticle(['title' => 'Hi']);

    expect($article->ai_tags)->toBe(['a', 'b']);
});

it('throws when JSON format gets invalid JSON back', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'not json at all']],
        ]),
    ]);

    $article = new RichArticle(['title' => 'Hi']);
    $article->ai_tags;
})->throws(\Parselynk\AiAttributes\Exceptions\AIAttributeException::class, 'invalid JSON');

it('casts a numeric response to int when format=number', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '42']],
        ]),
    ]);

    $article = new RichArticle(['title' => 'Hi']);

    expect($article->ai_sentiment)->toBe(42);
});

it('casts a decimal response to float when format=number', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '-12.5']],
        ]),
    ]);

    $article = new RichArticle(['title' => 'Hi']);

    expect($article->ai_sentiment)->toBe(-12.5);
});

it('throws when format=number gets non-numeric back', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'kinda positive']],
        ]),
    ]);

    $article = new RichArticle(['title' => 'Hi']);
    $article->ai_sentiment;
})->throws(\Parselynk\AiAttributes\Exceptions\AIAttributeException::class, 'non-numeric');

it('casts yes/no responses to bool when format=bool', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push(['content' => [['type' => 'text', 'text' => 'yes']]])
            ->push(['content' => [['type' => 'text', 'text' => 'NO']]]),
    ]);

    $a = new RichArticle(['title' => 'Click here to find out!']);
    $b = new RichArticle(['title' => 'Q3 earnings report']);

    expect($a->ai_is_clickbait)->toBeTrue();
    expect($b->ai_is_clickbait)->toBeFalse();
});

it('throws when format=bool gets unrecognized text back', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'maybe']],
        ]),
    ]);

    $article = new RichArticle(['title' => 'Hi']);
    $article->ai_is_clickbait;
})->throws(\Parselynk\AiAttributes\Exceptions\AIAttributeException::class, 'cast to bool');

it('regenerates when persona changes (cache key includes persona)', function () {
    // Two different persona configs should hit the AI separately.
    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push(['content' => [['type' => 'text', 'text' => 'first']]])
            ->push(['content' => [['type' => 'text', 'text' => 'second']]]),
    ]);

    $article = new RichArticle(['title' => 'Hi']);
    $article->ai_summary;          // no persona
    $article->ai_meta_description; // has SEO persona

    Http::assertSentCount(2);
});
