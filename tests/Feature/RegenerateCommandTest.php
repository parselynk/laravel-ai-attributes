<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Laravel\Ai\AnonymousAgent;
use Parselynk\AiAttributes\Jobs\GenerateAiAttributeJob;
use Parselynk\AiAttributes\Tests\Fixtures\PersistentArticle;
use Parselynk\AiAttributes\Tests\Fixtures\PlainArticle;

it('regenerates declared attributes for every record (sync)', function () {
    $calls = 0;
    AnonymousAgent::fake(function () use (&$calls) {
        $calls++;

        return "output {$calls}";
    });

    PersistentArticle::create(['title' => 'A', 'body' => 'foo']);
    PersistentArticle::create(['title' => 'B', 'body' => 'bar']);

    $this->artisan('ai:regenerate', [
        'model' => PersistentArticle::class,
    ])->assertExitCode(0);

    expect($calls)->toBe(2);
});

it('regenerates only the requested attribute when --attribute is passed', function () {
    $calls = 0;
    AnonymousAgent::fake(function () use (&$calls) {
        $calls++;

        return 'summary text';
    });

    PersistentArticle::create(['title' => 'A', 'body' => 'foo']);

    $this->artisan('ai:regenerate', [
        'model' => PersistentArticle::class,
        '--attribute' => ['summary'],
    ])->assertExitCode(0);

    expect($calls)->toBe(1);
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

it('clears the cache and calls the AI again on regenerate (no stale hits)', function () {
    AnonymousAgent::fake(['old', 'new']);

    $article = PersistentArticle::create(['title' => 'A', 'body' => 'foo']);
    expect($article->ai_summary)->toBe('old');

    $this->artisan('ai:regenerate', [
        'model' => PersistentArticle::class,
    ])->assertExitCode(0);

    // Cache was cleared, so the next fake response ('new') should now be cached
    expect($article->ai_summary)->toBe('new');
});
