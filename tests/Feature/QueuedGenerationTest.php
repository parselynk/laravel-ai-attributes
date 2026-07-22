<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Laravel\Ai\AnonymousAgent;
use Parselynk\AiAttributes\Exceptions\AIAttributeException;
use Parselynk\AiAttributes\Jobs\GenerateAiAttributeJob;
use Parselynk\AiAttributes\Tests\Fixtures\PersistentArticle;

it('generateAiAttributeAsync dispatches a job for the model and attribute', function () {
    Queue::fake();

    $article = PersistentArticle::create(['title' => 'Hi', 'body' => 'world']);

    $article->generateAiAttributeAsync('summary');

    Queue::assertPushed(GenerateAiAttributeJob::class, function ($job) use ($article) {
        return $job->model->is($article) && $job->attribute === 'summary';
    });
});

it('throws when trying to queue an unsaved model', function () {
    $article = new PersistentArticle(['title' => 'Hi']);

    $article->generateAiAttributeAsync('summary');
})->throws(AIAttributeException::class, 'Cannot queue');

it('validates the attribute exists before dispatching', function () {
    $article = PersistentArticle::create(['title' => 'Hi']);

    $article->generateAiAttributeAsync('does_not_exist');
})->throws(AIAttributeException::class, 'No AI attribute defined');

it('forwards the runtime persona override to the job and clears it on the model', function () {
    Queue::fake();

    $article = PersistentArticle::create(['title' => 'Hi']);

    $article->aiPersona('You are a poet.')->generateAiAttributeAsync('summary');

    Queue::assertPushed(GenerateAiAttributeJob::class, function ($job) {
        return $job->personaOverride === 'You are a poet.';
    });

    expect((fn () => $this->aiPersonaOverride)->call($article))->toBeNull();
});

it('the job populates the cache when handled', function () {
    AnonymousAgent::fake(['job-generated summary']);

    $article = PersistentArticle::create(['title' => 'Hi', 'body' => 'world']);

    $job = new GenerateAiAttributeJob($article, 'summary');
    $job->handle();

    // Re-reading should hit cache, not the AI. Fake with a different response
    // to prove the cache is used.
    AnonymousAgent::fake(['THIS SHOULD NOT BE USED']);
    expect($article->ai_summary)->toBe('job-generated summary');
});

it('aiAttributeOrNull returns null before the value is generated', function () {
    $article = PersistentArticle::create(['title' => 'Hi', 'body' => 'world']);

    expect($article->aiAttributeOrNull('summary'))->toBeNull();
});

it('aiAttributeOrNull returns the cached value without calling the AI', function () {
    $calls = 0;
    AnonymousAgent::fake(function () use (&$calls) {
        $calls++;

        return 'cached';
    });

    $article = PersistentArticle::create(['title' => 'Hi', 'body' => 'world']);
    $article->ai_summary; // populate cache

    expect($calls)->toBe(1);

    // Reading via aiAttributeOrNull should NOT call the AI again.
    expect($article->aiAttributeOrNull('summary'))->toBe('cached');
    expect($calls)->toBe(1);
});

it('hasAiAttribute is false before generation, true after', function () {
    $article = PersistentArticle::create(['title' => 'Hi', 'body' => 'world']);

    expect($article->hasAiAttribute('summary'))->toBeFalse();

    AnonymousAgent::fake(['now cached']);

    $article->ai_summary;

    expect($article->hasAiAttribute('summary'))->toBeTrue();
});
