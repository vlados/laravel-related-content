<?php

use Vlados\LaravelRelatedContent\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeValidEmbedding', function () {
    return $this->toBeArray()
        ->and(count($this->value))->toBeGreaterThan(0);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function createTestPost(array $attributes = []): \Vlados\LaravelRelatedContent\Tests\Fixtures\TestPost
{
    return \Vlados\LaravelRelatedContent\Tests\Fixtures\TestPost::create(array_merge([
        'title' => fake()->sentence(),
        'content' => fake()->paragraphs(3, true),
    ], $attributes));
}

function mockEmbeddingProvider(): \Mockery\MockInterface
{
    return Mockery::mock(\Vlados\LaravelRelatedContent\Contracts\EmbeddingProvider::class);
}

function generateFakeEmbedding(int $dimensions = 1536): array
{
    return array_map(fn () => fake()->randomFloat(6, -1, 1), range(1, $dimensions));
}
