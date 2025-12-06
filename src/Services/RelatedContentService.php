<?php

declare(strict_types=1);

namespace Vlados\LaravelRelatedContent\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Vlados\LaravelRelatedContent\Models\Embedding;
use Vlados\LaravelRelatedContent\Models\RelatedContent;

class RelatedContentService
{
    public function __construct(
        protected EmbeddingService $embeddingService
    ) {}

    /**
     * Generate embedding and find related content for a model.
     */
    public function sync(Model $model): void
    {
        // Step 1: Generate/update embedding
        $embedding = $this->embeddingService->embedModel($model);

        if (! $embedding) {
            return;
        }

        // Step 2: Find similar content
        $similar = $this->findSimilar($model, $embedding);

        // Step 3: Store related content links
        $this->storeRelatedContent($model, $similar);
    }

    /**
     * Find similar content across all configured model types.
     */
    public function findSimilar(Model $model, ?Embedding $embedding = null): Collection
    {
        $embedding = $embedding ?? $model->embedding;

        if (! $embedding) {
            return collect();
        }

        $modelTypes = config('related-content.models', []);
        $limit = config('related-content.max_related_items', 10);
        $threshold = config('related-content.similarity_threshold', 0.5);
        $embeddingsTable = config('related-content.tables.embeddings', 'embeddings');

        if (empty($modelTypes)) {
            // If no models configured, search same model type only
            $modelTypes = [get_class($model)];
        }

        $results = collect();

        foreach ($modelTypes as $modelClass) {
            $similar = $this->findSimilarOfType(
                $embedding,
                $modelClass,
                $model,
                (int) ceil($limit / count($modelTypes)),
                $threshold
            );

            $results = $results->merge($similar);
        }

        // Sort by similarity and take top N
        return $results
            ->sortByDesc('similarity')
            ->take($limit)
            ->values();
    }

    /**
     * Find similar content of a specific model type.
     */
    protected function findSimilarOfType(
        Embedding $sourceEmbedding,
        string $modelClass,
        Model $excludeModel,
        int $limit,
        float $threshold
    ): Collection {
        $embeddingsTable = config('related-content.tables.embeddings', 'embeddings');

        // Use pgvector's cosine distance operator
        $query = Embedding::query()
            ->select([
                'embeddable_type',
                'embeddable_id',
                DB::raw('1 - (embedding <=> ?) as similarity'),
            ])
            ->addBinding($sourceEmbedding->embedding, 'select')
            ->where('embeddable_type', $modelClass)
            ->whereRaw('1 - (embedding <=> ?) >= ?', [
                $sourceEmbedding->embedding,
                $threshold,
            ]);

        // Exclude source model if same type
        if ($modelClass === get_class($excludeModel)) {
            $query->where(function ($q) use ($excludeModel) {
                $q->where('embeddable_type', '!=', get_class($excludeModel))
                    ->orWhere('embeddable_id', '!=', $excludeModel->getKey());
            });
        }

        return $query
            ->orderByRaw('embedding <=> ?', [$sourceEmbedding->embedding])
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'related_type' => $row->embeddable_type,
                'related_id' => $row->embeddable_id,
                'similarity' => (float) $row->similarity,
            ]);
    }

    /**
     * Store related content links for a model.
     */
    protected function storeRelatedContent(Model $model, Collection $similar): void
    {
        $relatedContentTable = config('related-content.tables.related_content', 'related_content');

        // Delete existing related content for this source
        RelatedContent::where('source_type', get_class($model))
            ->where('source_id', $model->getKey())
            ->delete();

        // Insert new related content
        $records = $similar->map(fn ($item) => [
            'source_type' => get_class($model),
            'source_id' => $model->getKey(),
            'related_type' => $item['related_type'],
            'related_id' => $item['related_id'],
            'similarity' => $item['similarity'],
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        if (! empty($records)) {
            RelatedContent::insert($records);
        }
    }

    /**
     * Search for content by text query.
     *
     * @throws \InvalidArgumentException
     */
    public function search(string $query, array $modelTypes = [], int $limit = 10): Collection
    {
        $query = trim($query);

        if (empty($query)) {
            throw new \InvalidArgumentException('Search query cannot be empty');
        }

        $embedding = $this->embeddingService->generate($query);
        $embeddingsTable = config('related-content.tables.embeddings', 'embeddings');

        $dbQuery = Embedding::query()
            ->select([
                'embeddable_type',
                'embeddable_id',
                DB::raw('1 - (embedding <=> ?) as similarity'),
            ])
            ->addBinding($embedding, 'select')
            ->orderByRaw('embedding <=> ?', [$embedding])
            ->limit($limit);

        if (! empty($modelTypes)) {
            $dbQuery->whereIn('embeddable_type', $modelTypes);
        }

        return $dbQuery->get()->map(fn ($row) => [
            'type' => $row->embeddable_type,
            'id' => $row->embeddable_id,
            'similarity' => (float) $row->similarity,
        ]);
    }
}
