<?php

declare(strict_types=1);

namespace Vlados\LaravelRelatedContent\Commands;

use Illuminate\Console\Command;
use Vlados\LaravelRelatedContent\Jobs\SyncRelatedContentJob;
use Vlados\LaravelRelatedContent\Services\RelatedContentService;

class RebuildRelatedContentCommand extends Command
{
    protected $signature = 'related-content:rebuild
                            {model? : The model class to rebuild (optional, rebuilds all configured models if not specified)}
                            {--force : Process all models, even those with existing embeddings}
                            {--sync : Process synchronously instead of queuing}
                            {--chunk=100 : Number of models to process per chunk}';

    protected $description = 'Rebuild related content links for models (only missing by default, use --force for all)';

    public function handle(RelatedContentService $service): int
    {
        $modelClass = $this->argument('model');
        $force = (bool) $this->option('force');
        $sync = (bool) $this->option('sync');
        $chunkSize = (int) $this->option('chunk');

        $modelTypes = $modelClass
            ? [$modelClass]
            : config('related-content.models', []);

        if (empty($modelTypes)) {
            $this->error('No models configured. Either specify a model or configure related-content.models.');

            return self::FAILURE;
        }

        $modeText = $force ? 'force' : 'missing only';
        $this->info("Rebuilding related content ({$modeText})...");
        $this->newLine();

        foreach ($modelTypes as $modelClass) {
            if (! class_exists($modelClass)) {
                $this->warn("Model class {$modelClass} does not exist, skipping.");

                continue;
            }

            if (! method_exists($modelClass, 'embeddableFields')) {
                $this->warn("Model {$modelClass} does not use HasRelatedContent trait, skipping.");

                continue;
            }

            $this->processModel($service, $modelClass, $chunkSize, $force, $sync);
            $this->newLine();
        }

        return self::SUCCESS;
    }

    protected function processModel(
        RelatedContentService $service,
        string $modelClass,
        int $chunkSize,
        bool $force,
        bool $sync
    ): void {
        $query = $modelClass::query();

        // Only process models without embeddings unless --force is used
        if (! $force) {
            $query->whereDoesntHave('embedding');
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('No '.($force ? '' : 'missing ')."models to process for {$modelClass}.");

            return;
        }

        $this->info("Processing {$modelClass}".($force ? '' : ' (missing only)').'...');
        $this->info("Found {$total} models to process.");

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
}
