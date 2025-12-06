<?php

declare(strict_types=1);

namespace Vlados\LaravelRelatedContent\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

interface Embeddable
{
    /**
     * Get the fields that should be used to generate the embedding.
     *
     * @return array<string>
     */
    public function embeddableFields(): array;

    /**
     * Get the text content to be embedded.
     */
    public function toEmbeddableText(): string;

    /**
     * Get the embedding relationship.
     */
    public function embedding(): MorphOne;

    /**
     * Get the related content relationships.
     */
    public function relatedContent(): MorphMany;
}
