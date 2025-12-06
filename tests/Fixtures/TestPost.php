<?php

namespace Vlados\LaravelRelatedContent\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Vlados\LaravelRelatedContent\Concerns\HasRelatedContent;

class TestPost extends Model
{
    use HasRelatedContent;

    protected $table = 'test_posts';

    protected $guarded = [];

    public function embeddableFields(): array
    {
        return ['title', 'content'];
    }

    /**
     * Disable auto-sync during tests to prevent API calls.
     */
    protected function shouldSyncRelatedContent(): bool
    {
        return false;
    }
}
