<?php

use Illuminate\Support\Facades\Queue;
use Vlados\LaravelRelatedContent\Models\Embedding;
use Vlados\LaravelRelatedContent\Services\RelatedContentService;
use Vlados\LaravelRelatedContent\Tests\Fixtures\TestPost;

beforeEach(function () {
    config()->set('related-content.models', [TestPost::class]);
});

describe('RebuildRelatedContentCommand', function () {
    it('processes only models without embeddings by default', function () {
        $post1 = createTestPost(['title' => 'First Post']);
        $post2 = createTestPost(['title' => 'Second Post']);

        // Create embedding for post1 (simulating it already has one)
        Embedding::create([
            'embeddable_type' => TestPost::class,
            'embeddable_id' => $post1->id,
            'embedding' => json_encode(generateFakeEmbedding(10)),
            'model' => 'test',
            'dimensions' => 10,
        ]);

        // Mock the service to track which models get synced
        $syncedModels = [];
        $mockService = Mockery::mock(RelatedContentService::class);
        $mockService->shouldReceive('sync')
            ->andReturnUsing(function ($model) use (&$syncedModels) {
                $syncedModels[] = $model->id;
            });

        $this->app->instance(RelatedContentService::class, $mockService);

        $this->artisan('related-content:rebuild', [
            'model' => TestPost::class,
            '--sync' => true,
        ])
            ->expectsOutputToContain('missing only')
            ->expectsOutputToContain('1 models processed')
            ->assertExitCode(0);

        // Only post2 should be synced (post1 already has embedding)
        expect($syncedModels)->toBe([$post2->id]);
    });

    it('processes all models with --force flag', function () {
        $post1 = createTestPost(['title' => 'First Post']);
        $post2 = createTestPost(['title' => 'Second Post']);

        // Create embedding for post1 (simulating it already has one)
        Embedding::create([
            'embeddable_type' => TestPost::class,
            'embeddable_id' => $post1->id,
            'embedding' => json_encode(generateFakeEmbedding(10)),
            'model' => 'test',
            'dimensions' => 10,
        ]);

        // Mock the service to track which models get synced
        $syncedModels = [];
        $mockService = Mockery::mock(RelatedContentService::class);
        $mockService->shouldReceive('sync')
            ->andReturnUsing(function ($model) use (&$syncedModels) {
                $syncedModels[] = $model->id;
            });

        $this->app->instance(RelatedContentService::class, $mockService);

        $this->artisan('related-content:rebuild', [
            'model' => TestPost::class,
            '--force' => true,
            '--sync' => true,
        ])
            ->expectsOutputToContain('force')
            ->expectsOutputToContain('2 models processed')
            ->assertExitCode(0);

        // Both posts should be synced with --force
        expect($syncedModels)->toContain($post1->id)
            ->and($syncedModels)->toContain($post2->id);
    });

    it('shows message when no missing models to process', function () {
        $post = createTestPost(['title' => 'Test Post']);

        // Create embedding for the post
        Embedding::create([
            'embeddable_type' => TestPost::class,
            'embeddable_id' => $post->id,
            'embedding' => json_encode(generateFakeEmbedding(10)),
            'model' => 'test',
            'dimensions' => 10,
        ]);

        $this->artisan('related-content:rebuild', [
            'model' => TestPost::class,
            '--sync' => true,
        ])
            ->expectsOutputToContain('No missing models to process')
            ->assertExitCode(0);
    });

    it('fails when no models configured and no model specified', function () {
        config()->set('related-content.models', []);

        $this->artisan('related-content:rebuild')
            ->expectsOutput('No models configured. Either specify a model or configure related-content.models.')
            ->assertExitCode(1);
    });

    it('skips non-existent model classes', function () {
        config()->set('related-content.models', [
            'App\\Models\\NonExistent',
        ]);

        $this->artisan('related-content:rebuild', [
            '--sync' => true,
        ])
            ->expectsOutput('Model class App\\Models\\NonExistent does not exist, skipping.')
            ->assertExitCode(0);
    });

    it('skips models without HasRelatedContent trait', function () {
        config()->set('related-content.models', [
            \Illuminate\Database\Eloquent\Model::class,
        ]);

        $this->artisan('related-content:rebuild', [
            '--sync' => true,
        ])
            ->expectsOutputToContain('does not use HasRelatedContent trait, skipping')
            ->assertExitCode(0);
    });

    it('queues jobs when sync flag is not set', function () {
        Queue::fake();

        createTestPost(['title' => 'Test Post']);

        $this->artisan('related-content:rebuild', [
            'model' => TestPost::class,
            '--force' => true,
        ])
            ->expectsOutputToContain('queued')
            ->assertExitCode(0);
    });

    it('processes all configured models when no model specified', function () {
        $post1 = createTestPost(['title' => 'First Post']);
        $post2 = createTestPost(['title' => 'Second Post']);

        // Mock the service to track which models get synced
        $syncedModels = [];
        $mockService = Mockery::mock(RelatedContentService::class);
        $mockService->shouldReceive('sync')
            ->andReturnUsing(function ($model) use (&$syncedModels) {
                $syncedModels[] = $model->id;
            });

        $this->app->instance(RelatedContentService::class, $mockService);

        $this->artisan('related-content:rebuild', [
            '--force' => true,
            '--sync' => true,
        ])
            ->expectsOutputToContain('Rebuilding related content')
            ->expectsOutputToContain('2 models processed')
            ->assertExitCode(0);

        // Both posts should be synced
        expect($syncedModels)->toContain($post1->id)
            ->and($syncedModels)->toContain($post2->id);
    });
});
