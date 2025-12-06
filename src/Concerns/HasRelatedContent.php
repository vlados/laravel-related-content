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
        static::saved(function (Model $model) {
            if ($model->shouldSyncRelatedContent()) {
                SyncRelatedContentJob::dispatch($model);
            }
        });

        static::deleted(function (Model $model) {
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
     */
    public function getRelatedModels(?int $limit = null): Collection
    {
        $limit = $limit ?? config('related-content.max_related_items', 10);

        return $this->relatedContent()
            ->with('related')
            ->limit($limit)
            ->get()
            ->pluck('related')
            ->filter();
    }

    /**
     * Get related models of a specific type.
     *
     * @param  class-string  $modelClass
     */
    public function getRelatedOfType(string $modelClass, int $limit = 5): Collection
    {
        return $this->relatedContent()
            ->where('related_type', $modelClass)
            ->with('related')
            ->limit($limit)
            ->get()
            ->pluck('related')
            ->filter();
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
