<?php

use Pgvector\Laravel\Vector;
use Vlados\LaravelRelatedContent\Contracts\EmbeddingProvider;
use Vlados\LaravelRelatedContent\Models\Embedding;
use Vlados\LaravelRelatedContent\Services\EmbeddingService;
use Vlados\LaravelRelatedContent\Tests\Fixtures\TestPost;

beforeEach(function () {
    config()->set('related-content.models', [TestPost::class]);
});

describe('GenerateEmbeddingsCommand', function () {
    it('generates embeddings for all models', function () {
        $post1 = createTestPost(['title' => 'First Post']);
        $post2 = createTestPost(['title' => 'Second Post']);

        $mockProvider = Mockery::mock(EmbeddingProvider::class);
        $mockProvider->shouldReceive('generate')
            ->andReturn(new Vector(generateFakeEmbedding(10)));
        $mockProvider->shouldReceive('model')
            ->andReturn('text-embedding-3-small');
        $mockProvider->shouldReceive('dimensions')
            ->andReturn(10);

        $this->app->instance(EmbeddingProvider::class, $mockProvider);

        $this->artisan('related-content:embeddings', [
            'model' => TestPost::class,
        ])
            ->expectsOutputToContain('Generating embeddings for')
            ->expectsOutputToContain('Processed: 2')
            ->assertExitCode(0);

        expect(Embedding::count())->toBe(2);
    });

    it('fails when model class does not exist', function () {
        $this->artisan('related-content:embeddings', [
            'model' => 'App\\Models\\NonExistent',
        ])
            ->expectsOutput('Model class App\\Models\\NonExistent does not exist.')
            ->assertExitCode(1);
    });

    it('fails when model does not use HasRelatedContent trait', function () {
        $this->artisan('related-content:embeddings', [
            'model' => \Illuminate\Database\Eloquent\Model::class,
        ])
            ->expectsOutputToContain('must use the HasRelatedContent trait')
            ->assertExitCode(1);
    });

    it('respects chunk size option', function () {
        for ($i = 0; $i < 5; $i++) {
            createTestPost(['title' => "Post {$i}"]);
        }

        $mockProvider = Mockery::mock(EmbeddingProvider::class);
        $mockProvider->shouldReceive('generate')
            ->andReturn(new Vector(generateFakeEmbedding(10)));
        $mockProvider->shouldReceive('model')
            ->andReturn('text-embedding-3-small');
        $mockProvider->shouldReceive('dimensions')
            ->andReturn(10);

        $this->app->instance(EmbeddingProvider::class, $mockProvider);

        $this->artisan('related-content:embeddings', [
            'model' => TestPost::class,
            '--chunk' => 2,
        ])->assertExitCode(0);

        expect(Embedding::count())->toBe(5);
    });
});

describe('RebuildRelatedContentCommand', function () {
    it('rebuilds related content for specified model with sync flag', function () {
        $post1 = createTestPost(['title' => 'First Post']);
        $post2 = createTestPost(['title' => 'Second Post']);

        // Create mock that will be used
        $mockProvider = Mockery::mock(EmbeddingProvider::class);
        $mockProvider->shouldReceive('generate')
            ->andReturn(new Vector(generateFakeEmbedding(10)));
        $mockProvider->shouldReceive('model')
            ->andReturn('text-embedding-3-small');
        $mockProvider->shouldReceive('dimensions')
            ->andReturn(10);

        $this->app->instance(EmbeddingProvider::class, $mockProvider);

        // Mock the service to avoid pgvector operations
        $mockEmbeddingService = Mockery::mock(EmbeddingService::class);
        $mockEmbeddingService->shouldReceive('embedModel')
            ->andReturn(null); // Return null to skip findSimilar

        $this->app->instance(EmbeddingService::class, $mockEmbeddingService);

        $this->artisan('related-content:rebuild', [
            'model' => TestPost::class,
            '--sync' => true,
        ])
            ->expectsOutputToContain('Rebuilding related content for')
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

    it('processes queued jobs when sync flag is not set', function () {
        Queue::fake();

        createTestPost(['title' => 'Test Post']);

        $this->artisan('related-content:rebuild', [
            'model' => TestPost::class,
        ])
            ->expectsOutputToContain('queued')
            ->assertExitCode(0);
    });
});
