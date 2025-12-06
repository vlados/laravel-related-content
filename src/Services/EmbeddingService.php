<?php

declare(strict_types=1);

namespace Vlados\LaravelRelatedContent\Services;

use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\Vector;
use Vlados\LaravelRelatedContent\Contracts\EmbeddingProvider;
use Vlados\LaravelRelatedContent\Models\Embedding;

class EmbeddingService
{
    public function __construct(
        protected EmbeddingProvider $provider
    ) {}

    /**
     * Generate an embedding vector for the given text.
     */
    public function generate(string $text): Vector
    {
        return $this->provider->generate($text);
    }

    /**
     * Generate and store an embedding for a model.
     */
    public function embedModel(Model $model): ?Embedding
    {
        if (! method_exists($model, 'toEmbeddableText')) {
            throw new \InvalidArgumentException(
                'Model must use HasRelatedContent trait or implement toEmbeddableText method'
            );
        }

        $text = $model->toEmbeddableText();

        if (empty(trim($text))) {
            return null;
        }

        $vector = $this->generate($text);

        return Embedding::updateOrCreate(
            [
                'embeddable_type' => get_class($model),
                'embeddable_id' => $model->getKey(),
            ],
            [
                'embedding' => $vector,
                'model' => $this->provider->model(),
                'dimensions' => $this->provider->dimensions(),
            ]
        );
    }

    /**
     * Get the configured dimensions.
     */
    public function dimensions(): int
    {
        return $this->provider->dimensions();
    }

    /**
     * Get the model name being used.
     */
    public function model(): string
    {
        return $this->provider->model();
    }
}
