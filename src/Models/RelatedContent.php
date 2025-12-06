<?php

declare(strict_types=1);

namespace Vlados\LaravelRelatedContent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RelatedContent extends Model
{
    protected $fillable = [
        'source_type',
        'source_id',
        'related_type',
        'related_id',
        'similarity',
    ];

    protected $casts = [
        'similarity' => 'float',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = config('related-content.tables.related_content', 'related_content');
    }

    /**
     * Get the source model.
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the related model.
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
