<?php

declare(strict_types=1);

namespace Vlados\LaravelRelatedContent\Commands;

use Illuminate\Console\Command;
use Vlados\LaravelRelatedContent\Jobs\SyncRelatedContentJob;
use Vlados\LaravelRelatedContent\Services\RelatedContentService;

class RebuildRelatedContentCommand extends Command
{
    protected $signature = 'related-content:rebuild
                            {model? : The model class to rebuild (optional, rebuilds all if not specified)}
                            {--sync : Process synchronously instead of queuing}
                            {--chunk=100 : Number of models to process per chunk}';

    protected $description = 'Rebuild related content links for models';

    public function handle(RelatedContentService $service): int
    {
        $modelClass = $this->argument('model');
        $sync = $this->option('sync');
        $chunkSize = (int) $this->option('chunk');

        $modelTypes = $modelClass
            ? [$modelClass]
            : config('related-content.models', []);

        if (empty($modelTypes)) {
            $this->error('No models configured. Either specify a model or configure related-content.models.');

            return self::FAILURE;
        }

        foreach ($modelTypes as $modelClass) {
            if (!class_exists($modelClass)) {
                $this->warn("Model class {$modelClass} does not exist, skipping.");

                continue;
            }

            $this->info("Rebuilding related content for {$modelClass}...");

            $query = $modelClass::query();
            $total = $query->count();
            $processed = 0;

            $bar = $this->output->createProgressBar($total);
            $bar->start();

            $query->chunkById($chunkSize, function ($models) use ($service, $sync, &$processed, $bar) {
                foreach ($models as $model) {
                    if ($sync) {
                        $service->sync($model);
                    } else {
                        SyncRelatedContentJob::dispatch($model);
                    }

                    $processed++;
                    $bar->advance();
                }
            });

            $bar->finish();
            $this->newLine(2);

            $method = $sync ? 'processed' : 'queued';
            $this->info("{$processed} models {$method} for {$modelClass}");
        }

        return self::SUCCESS;
    }
}
