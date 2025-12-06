<?php

declare(strict_types=1);

namespace Vlados\LaravelRelatedContent\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;
use Vlados\LaravelRelatedContent\Jobs\SyncRelatedContentJob;
use Vlados\LaravelRelatedContent\Models\Embedding;
use Vlados\LaravelRelatedContent\Models\RelatedContent;

/**
 * @mixin Model
 */
trait HasRelatedContent
{
    /**
     * Boot the trait.
     */
    public static function bootHasRelatedContent(): void
    {
        static::saved(function (self $model) {
            if ($model->shouldSyncRelatedContent()) {
                SyncRelatedContentJob::dispatch($model);
            }
        });

        static::deleted(function (self $model) {
            // Clean up embedding and related content when model is deleted
            $model->embedding()->delete();
            $model->relatedContent()->delete();
            $model->relatedTo()->delete();
        });
    }

    /**
     * Define which fields should be used for embedding generation.
     * Override this in your model.
     *
     * @return array<string>
     */
    abstract public function embeddableFields(): array;

    /**
     * Get the text content to be embedded.
     */
    public function toEmbeddableText(): string
    {
        $texts = [];

        foreach ($this->embeddableFields() as $field) {
            $value = $this->getAttribute($field);

            // Handle Spatie Translatable
            if (method_exists($this, 'getTranslations')) {
                $translations = $this->getTranslations($field);
                if (! empty($translations)) {
                    $value = implode("\n", array_filter($translations));
                }
            }

            if ($value) {
                $texts[] = strip_tags((string) $value);
            }
        }

        return implode("\n\n", array_filter($texts));
    }

    /**
     * Get the embedding relationship.
     */
    public function embedding(): MorphOne
    {
        return $this->morphOne(Embedding::class, 'embeddable');
    }

    /**
     * Get the related content (this model as source).
     */
    public function relatedContent(): MorphMany
    {
        return $this->morphMany(RelatedContent::class, 'source')
            ->orderByDesc('similarity');
    }

    /**
     * Get content that relates TO this model (this model as related).
     */
    public function relatedTo(): MorphMany
    {
        return $this->morphMany(RelatedContent::class, 'related');
    }

    /**
     * Get the related models with eager loading.
     * Queries both directions: where this model is source OR related.
     */
    public function getRelatedModels(?int $limit = null): Collection
    {
        $limit = $limit ?? config('related-content.max_related_items', 10);

        // Get models where this is the source
        /** @var \Illuminate\Database\Eloquent\Collection<int, RelatedContent> $sourceRecords */
        $sourceRecords = $this->relatedContent()->with('related')->get();
        $asSource = $sourceRecords->map(fn (RelatedContent $rc) => [
            'model' => $rc->related,
            'similarity' => $rc->similarity,
        ]);

        // Get models where this is the related (reverse direction)
        /** @var \Illuminate\Database\Eloquent\Collection<int, RelatedContent> $relatedRecords */
        $relatedRecords = $this->relatedTo()->with('source')->get();
        $asRelated = $relatedRecords->map(fn (RelatedContent $rc) => [
            'model' => $rc->source,
            'similarity' => $rc->similarity,
        ]);

        // Merge, deduplicate, sort by similarity, and return models
        return $asSource->concat($asRelated)
            ->filter(fn ($item) => $item['model'] !== null)
            ->unique(fn ($item) => get_class($item['model']).':'.$item['model']->getKey())
            ->sortByDesc('similarity')
            ->take($limit)
            ->pluck('model')
            ->values();
    }

    /**
     * Get related models of a specific type.
     * Queries both directions: where this model is source OR related.
     *
     * @param  class-string  $modelClass
     */
    public function getRelatedOfType(string $modelClass, int $limit = 5): Collection
    {
        // Get models where this is the source
        /** @var \Illuminate\Database\Eloquent\Collection<int, RelatedContent> $sourceRecords */
        $sourceRecords = $this->relatedContent()
            ->where('related_type', $modelClass)
            ->with('related')
            ->get();
        $asSource = $sourceRecords->map(fn (RelatedContent $rc) => [
            'model' => $rc->related,
            'similarity' => $rc->similarity,
        ]);

        // Get models where this is the related (reverse direction)
        /** @var \Illuminate\Database\Eloquent\Collection<int, RelatedContent> $relatedRecords */
        $relatedRecords = $this->relatedTo()
            ->where('source_type', $modelClass)
            ->with('source')
            ->get();
        $asRelated = $relatedRecords->map(fn (RelatedContent $rc) => [
            'model' => $rc->source,
            'similarity' => $rc->similarity,
        ]);

        // Merge, deduplicate, sort by similarity, and return models
        return $asSource->concat($asRelated)
            ->filter(fn ($item) => $item['model'] !== null)
            ->unique(fn ($item) => $item['model']->getKey())
            ->sortByDesc('similarity')
            ->take($limit)
            ->pluck('model')
            ->values();
    }

    /**
     * Determine if related content should be synced.
     */
    protected function shouldSyncRelatedContent(): bool
    {
        // Only sync if embeddable fields changed
        foreach ($this->embeddableFields() as $field) {
            if ($this->isDirty($field)) {
                return true;
            }
        }

        // Also sync if there's no embedding yet
        if (! $this->embedding) {
            return true;
        }

        return false;
    }

    /**
     * Force sync related content (useful for manual triggering).
     */
    public function syncRelatedContent(): void
    {
        SyncRelatedContentJob::dispatchSync($this);
    }

    /**
     * Queue sync related content.
     */
    public function queueSyncRelatedContent(): void
    {
        SyncRelatedContentJob::dispatch($this);
    }
}
