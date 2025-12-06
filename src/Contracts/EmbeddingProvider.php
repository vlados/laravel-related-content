<?php

declare(strict_types=1);

namespace Vlados\LaravelRelatedContent\Contracts;

use Pgvector\Laravel\Vector;

interface EmbeddingProvider
{
    /**
     * Generate an embedding vector for the given text.
     */
    public function generate(string $text): Vector;

    /**
     * Get the number of dimensions for embeddings from this provider.
     */
    public function dimensions(): int;

    /**
     * Get the model name being used.
     */
    public function model(): string;
}
