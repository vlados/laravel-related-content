<?php

declare(strict_types=1);

namespace Vlados\LaravelRelatedContent\Handlers;

use Pgvector\Laravel\Vector;
use Vlados\LaravelRelatedContent\Contracts\EmbeddingProvider;

/**
 * A null handler that returns zero vectors.
 *
 * Used when the package is disabled or not properly configured
 * (e.g., missing API keys in testing environments).
 */
class NullHandler implements EmbeddingProvider
{
    protected int $dimensions;

    public function __construct(int $dimensions = 1536)
    {
        $this->dimensions = $dimensions;
    }

    public function generate(string $text): Vector
    {
        // Return a zero vector of the configured dimensions
        return new Vector(array_fill(0, $this->dimensions, 0.0));
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function model(): string
    {
        return 'null';
    }
}
