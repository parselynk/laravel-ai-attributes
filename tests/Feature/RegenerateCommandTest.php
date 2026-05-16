<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Parselynk\AiAttributes\Jobs\GenerateAiAttributeJob;
use Parselynk\AiAttributes\Tests\Fixtures\PersistentArticle;
use Parselynk\AiAttributes\Tests\Fixtures\PlainArticle;

beforeEach(function () {
    config()->set('ai-attributes.default', 'claude');
});

it('regenerates declared attributes for every record (sync)', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push(['content' => [['type' => 'text', 'text' => 'one']]])
            ->push(['content' => [['type' => 'text', 'text' => 'two']]]),
    ]);

    PersistentArticle::create(['title' => 'A', 'body' => 'foo']);
    PersistentArticle::create(['title' => 'B', 'body' => 'bar']);

    $this->artisan('ai:regenerate', [
        'model' => PersistentArticle::class,
    ])->assertExitCode(0);

    Http::assertSentCount(2);
});

it('regenerates only the requested attribute when --attribute is passed', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'summary text']],
        ]),
    ]);

    PersistentArticle::create(['title' => 'A', 'body' => 'foo']);

    $this->artisan('ai:regenerate', [
        'model' => PersistentArticle::class,
        '--attribute' => ['summary'],
    ])->assertExitCode(0);

    Http::assertSentCount(1);
});

it('dispatches to the queue when --queue is passed', function () {
    Queue::fake();

    PersistentArticle::create(['title' => 'A']);
    PersistentArticle::create(['title' => 'B']);

    $this->artisan('ai:regenerate', [
        'model' => PersistentArticle::class,
        '--queue' => true,
    ])->assertExitCode(0);

    Queue::assertPushed(GenerateAiAttributeJob::class, 2);
});

it('fails when the model class does not exist', function () {
    $this->artisan('ai:regenerate', [
        'model' => 'App\\Models\\Nonexistent',
    ])
        ->expectsOutputToContain('does not exist')
        ->assertExitCode(1);
});

it('fails when the model does not use HasAIAttributes', function () {
    $this->artisan('ai:regenerate', [
        'model' => PlainArticle::class,
    ])
        ->expectsOutputToContain('HasAIAttributes')
        ->assertExitCode(1);
});

it('fails when an unknown attribute is requested', function () {
    PersistentArticle::create(['title' => 'A']);

    $this->artisan('ai:regenerate', [
        'model' => PersistentArticle::class,
        '--attribute' => ['nope'],
    ])
        ->expectsOutputToContain('Unknown attributes: nope')
        ->assertExitCode(1);
});

it('clears the cache and calls the API again on regenerate (no stale hits)', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push(['content' => [['type' => 'text', 'text' => 'old']]])
            ->push(['content' => [['type' => 'text', 'text' => 'new']]]),
    ]);

    $article = PersistentArticle::create(['title' => 'A', 'body' => 'foo']);
    expect($article->ai_summary)->toBe('old');
    Http::assertSentCount(1);

    $this->artisan('ai:regenerate', [
        'model' => PersistentArticle::class,
    ])->assertExitCode(0);

    // If forget did NOT work, regenerate would hit the cached "old" — no new API call.
    // Two HTTP calls means the cache was cleared and the AI was hit again.
    Http::assertSentCount(2);
});
