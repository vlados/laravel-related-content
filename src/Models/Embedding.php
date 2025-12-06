<?php

declare(strict_types=1);

namespace Vlados\LaravelRelatedContent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Pgvector\Laravel\Vector;

class Embedding extends Model
{
    protected $fillable = [
        'embeddable_type',
        'embeddable_id',
        'embedding',
        'model',
        'dimensions',
    ];

    protected $casts = [
        'embedding' => Vector::class,
        'dimensions' => 'integer',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = config('related-content.tables.embeddings', 'embeddings');
    }

    /**
     * Get the parent embeddable model.
     */
    public function embeddable(): MorphTo
    {
        return $this->morphTo();
    }
}
