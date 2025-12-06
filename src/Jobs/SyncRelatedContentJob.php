<?php

declare(strict_types=1);

namespace Vlados\LaravelRelatedContent\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Vlados\LaravelRelatedContent\Events\RelatedContentSynced;
use Vlados\LaravelRelatedContent\Services\RelatedContentService;

class SyncRelatedContentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    public function __construct(
        public Model $model
    ) {
        $connection = config('related-content.queue.connection');
        $queue = config('related-content.queue.name');

        if ($connection && $connection !== 'default') {
            $this->onConnection($connection);
        }

        if ($queue && $queue !== 'default') {
            $this->onQueue($queue);
        }
    }

    public function handle(RelatedContentService $service): void
    {
        $service->sync($this->model);

        event(new RelatedContentSynced($this->model));
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return get_class($this->model).':'.$this->model->getKey();
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(5);
    }
}
