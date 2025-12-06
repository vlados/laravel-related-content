<?php

use Vlados\LaravelRelatedContent\Models\Embedding;
use Vlados\LaravelRelatedContent\Models\RelatedContent;
use Vlados\LaravelRelatedContent\Tests\Fixtures\TestPost;

describe('HasRelatedContent Trait', function () {
    it('returns embeddable fields', function () {
        $post = new TestPost;

        expect($post->embeddableFields())->toBe(['title', 'content']);
    });

    it('converts model to embeddable text', function () {
        $post = createTestPost([
            'title' => 'My Title',
            'content' => '<p>My <strong>Content</strong></p>',
        ]);

        $text = $post->toEmbeddableText();

        expect($text)->toContain('My Title')
            ->and($text)->toContain('My Content')
            ->and($text)->not->toContain('<p>')
            ->and($text)->not->toContain('<strong>');
    });

    it('has embedding relationship', function () {
        $post = createTestPost();

        expect($post->embedding())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphOne::class);
    });

    it('has relatedContent relationship', function () {
        $post = createTestPost();

        expect($post->relatedContent())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class);
    });

    it('has relatedTo relationship', function () {
        $post = createTestPost();

        expect($post->relatedTo())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class);
    });

    it('cleans up on model deletion', function () {
        $post = createTestPost();

        // Create embedding
        Embedding::create([
            'embeddable_type' => TestPost::class,
            'embeddable_id' => $post->id,
            'embedding' => json_encode(generateFakeEmbedding(10)),
            'model' => 'test',
            'dimensions' => 10,
        ]);

        // Create related content
        $relatedPost = createTestPost();
        RelatedContent::create([
            'source_type' => TestPost::class,
            'source_id' => $post->id,
            'related_type' => TestPost::class,
            'related_id' => $relatedPost->id,
            'similarity' => 0.9,
        ]);

        expect(Embedding::count())->toBe(1);
        expect(RelatedContent::count())->toBe(1);

        $post->delete();

        expect(Embedding::count())->toBe(0);
        expect(RelatedContent::count())->toBe(0);
    });

    it('gets related models', function () {
        $post1 = createTestPost(['title' => 'Post 1']);
        $post2 = createTestPost(['title' => 'Post 2']);
        $post3 = createTestPost(['title' => 'Post 3']);

        RelatedContent::create([
            'source_type' => TestPost::class,
            'source_id' => $post1->id,
            'related_type' => TestPost::class,
            'related_id' => $post2->id,
            'similarity' => 0.9,
        ]);

        RelatedContent::create([
            'source_type' => TestPost::class,
            'source_id' => $post1->id,
            'related_type' => TestPost::class,
            'related_id' => $post3->id,
            'similarity' => 0.8,
        ]);

        $related = $post1->getRelatedModels();

        expect($related)->toHaveCount(2)
            ->and($related->first()->id)->toBe($post2->id)
            ->and($related->last()->id)->toBe($post3->id);
    });

    it('gets related models of specific type', function () {
        $post1 = createTestPost(['title' => 'Post 1']);
        $post2 = createTestPost(['title' => 'Post 2']);

        RelatedContent::create([
            'source_type' => TestPost::class,
            'source_id' => $post1->id,
            'related_type' => TestPost::class,
            'related_id' => $post2->id,
            'similarity' => 0.9,
        ]);

        $related = $post1->getRelatedOfType(TestPost::class);

        expect($related)->toHaveCount(1)
            ->and($related->first()->id)->toBe($post2->id);
    });

    it('respects limit when getting related models', function () {
        $post1 = createTestPost();

        for ($i = 0; $i < 5; $i++) {
            $relatedPost = createTestPost(['title' => "Related Post {$i}"]);
            RelatedContent::create([
                'source_type' => TestPost::class,
                'source_id' => $post1->id,
                'related_type' => TestPost::class,
                'related_id' => $relatedPost->id,
                'similarity' => 0.9 - ($i * 0.1),
            ]);
        }

        $related = $post1->getRelatedModels(3);

        expect($related)->toHaveCount(3);
    });
});
