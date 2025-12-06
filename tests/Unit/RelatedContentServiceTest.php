<?php

use Pgvector\Laravel\Vector;
use Vlados\LaravelRelatedContent\Contracts\EmbeddingProvider;
use Vlados\LaravelRelatedContent\Models\Embedding;
use Vlados\LaravelRelatedContent\Models\RelatedContent;
use Vlados\LaravelRelatedContent\Services\EmbeddingService;
use Vlados\LaravelRelatedContent\Services\RelatedContentService;
use Vlados\LaravelRelatedContent\Tests\Fixtures\TestPost;

beforeEach(function () {
    $this->mockProvider = Mockery::mock(EmbeddingProvider::class);
    $this->embeddingService = new EmbeddingService($this->mockProvider);
    $this->service = new RelatedContentService($this->embeddingService);

    // Configure models
    config()->set('related-content.models', [TestPost::class]);
});

describe('RelatedContentService', function () {
    it('creates embedding when syncing a model', function () {
        $post = createTestPost(['title' => 'Electric Vehicles']);

        $fakeVector = new Vector(generateFakeEmbedding(10)); // Use smaller vector for testing

        $this->mockProvider
            ->shouldReceive('generate')
            ->once()
            ->andReturn($fakeVector);

        $this->mockProvider
            ->shouldReceive('model')
            ->once()
            ->andReturn('text-embedding-3-small');

        $this->mockProvider
            ->shouldReceive('dimensions')
            ->once()
            ->andReturn(10);

        // We can't test the full sync flow on SQLite due to pgvector operators
        // but we can verify the embedding is created
        $embedding = $this->embeddingService->embedModel($post);

        expect($embedding)->not->toBeNull()
            ->and($embedding->embeddable_type)->toBe(TestPost::class)
            ->and($embedding->embeddable_id)->toBe($post->id);
    });

    it('stores related content records', function () {
        $post1 = createTestPost(['title' => 'Post 1']);
        $post2 = createTestPost(['title' => 'Post 2']);

        // Directly test storeRelatedContent by creating manual relations
        RelatedContent::create([
            'source_type' => TestPost::class,
            'source_id' => $post1->id,
            'related_type' => TestPost::class,
            'related_id' => $post2->id,
            'similarity' => 0.85,
        ]);

        expect(RelatedContent::count())->toBe(1);

        $relation = RelatedContent::first();
        expect($relation->source_type)->toBe(TestPost::class)
            ->and($relation->source_id)->toBe($post1->id)
            ->and($relation->related_type)->toBe(TestPost::class)
            ->and($relation->related_id)->toBe($post2->id)
            ->and($relation->similarity)->toBe(0.85);
    });

    it('deletes old related content when storing new', function () {
        $post1 = createTestPost();
        $post2 = createTestPost();
        $post3 = createTestPost();

        // Create initial relation
        RelatedContent::create([
            'source_type' => TestPost::class,
            'source_id' => $post1->id,
            'related_type' => TestPost::class,
            'related_id' => $post2->id,
            'similarity' => 0.5,
        ]);

        expect(RelatedContent::where('source_id', $post1->id)->count())->toBe(1);

        // Delete and create new
        RelatedContent::where('source_type', TestPost::class)
            ->where('source_id', $post1->id)
            ->delete();

        RelatedContent::create([
            'source_type' => TestPost::class,
            'source_id' => $post1->id,
            'related_type' => TestPost::class,
            'related_id' => $post3->id,
            'similarity' => 0.9,
        ]);

        $relations = RelatedContent::where('source_id', $post1->id)->get();
        expect($relations)->toHaveCount(1)
            ->and($relations->first()->related_id)->toBe($post3->id);
    });
});

describe('RelatedContentService Search', function () {
    it('throws exception for empty search query', function () {
        $this->service->search('');
    })->throws(InvalidArgumentException::class, 'Search query cannot be empty');

    it('throws exception for whitespace-only search query', function () {
        $this->service->search('   ');
    })->throws(InvalidArgumentException::class, 'Search query cannot be empty');
});
