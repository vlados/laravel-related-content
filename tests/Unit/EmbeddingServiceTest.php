<?php

use Pgvector\Laravel\Vector;
use Vlados\LaravelRelatedContent\Contracts\EmbeddingProvider;
use Vlados\LaravelRelatedContent\Models\Embedding;
use Vlados\LaravelRelatedContent\Services\EmbeddingService;
use Vlados\LaravelRelatedContent\Tests\Fixtures\TestPost;

beforeEach(function () {
    $this->mockProvider = Mockery::mock(EmbeddingProvider::class);
    $this->service = new EmbeddingService($this->mockProvider);
});

describe('EmbeddingService', function () {
    it('generates embedding for text', function () {
        $fakeVector = new Vector(generateFakeEmbedding(1536));

        $this->mockProvider
            ->shouldReceive('generate')
            ->once()
            ->with('Hello world')
            ->andReturn($fakeVector);

        $result = $this->service->generate('Hello world');

        expect($result)->toBeInstanceOf(Vector::class);
    });

    it('embeds a model and stores the embedding', function () {
        $post = createTestPost([
            'title' => 'Test Title',
            'content' => 'Test Content',
        ]);

        $fakeVector = new Vector(generateFakeEmbedding(1536));

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
            ->andReturn(1536);

        $embedding = $this->service->embedModel($post);

        expect($embedding)->toBeInstanceOf(Embedding::class)
            ->and($embedding->embeddable_type)->toBe(TestPost::class)
            ->and($embedding->embeddable_id)->toBe($post->id)
            ->and($embedding->model)->toBe('text-embedding-3-small')
            ->and($embedding->dimensions)->toBe(1536);
    });

    it('updates existing embedding when model is re-embedded', function () {
        $post = createTestPost();

        $fakeVector1 = new Vector(generateFakeEmbedding(1536));
        $fakeVector2 = new Vector(generateFakeEmbedding(1536));

        $this->mockProvider
            ->shouldReceive('generate')
            ->twice()
            ->andReturn($fakeVector1, $fakeVector2);

        $this->mockProvider
            ->shouldReceive('model')
            ->twice()
            ->andReturn('text-embedding-3-small');

        $this->mockProvider
            ->shouldReceive('dimensions')
            ->twice()
            ->andReturn(1536);

        $embedding1 = $this->service->embedModel($post);
        $embedding2 = $this->service->embedModel($post);

        expect($embedding1->id)->toBe($embedding2->id);
        expect(Embedding::count())->toBe(1);
    });

    it('throws exception when model does not have toEmbeddableText method', function () {
        $invalidModel = new class extends \Illuminate\Database\Eloquent\Model
        {
            protected $table = 'test_posts';
        };

        $this->service->embedModel($invalidModel);
    })->throws(InvalidArgumentException::class);

    it('returns null for empty embeddable text', function () {
        $post = createTestPost([
            'title' => '',
            'content' => '',
        ]);

        $result = $this->service->embedModel($post);

        expect($result)->toBeNull();
    });
});
