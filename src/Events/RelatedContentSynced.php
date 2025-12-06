<?php

declare(strict_types=1);

namespace Vlados\LaravelRelatedContent\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RelatedContentSynced
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Model $model
    ) {}
}
