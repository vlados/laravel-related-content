<?php

declare(strict_types=1);

namespace Vlados\LaravelRelatedContent\Commands;

use Illuminate\Console\Command;
use Vlados\LaravelRelatedContent\Services\EmbeddingService;

class GenerateEmbeddingsCommand extends Command
{
    protected $signature = 'related-content:embeddings
                            {model : The model class to generate embeddings for}
                            {--chunk=100 : Number of models to process per chunk}';

    protected $description = 'Generate embeddings for all instances of a model';

    public function handle(EmbeddingService $service): int
    {
        $modelClass = $this->argument('model');
        $chunkSize = (int) $this->option('chunk');

        if (!class_exists($modelClass)) {
            $this->error("Model class {$modelClass} does not exist.");

            return self::FAILURE;
        }

        if (!method_exists($modelClass, 'embeddableFields')) {
            $this->error("Model {$modelClass} must use the HasRelatedContent trait.");

            return self::FAILURE;
        }

        $this->info("Generating embeddings for {$modelClass}...");

        $query = $modelClass::query();
        $total = $query->count();
        $processed = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById($chunkSize, function ($models) use ($service, &$processed, &$errors, $bar) {
            foreach ($models as $model) {
                try {
                    $service->embedModel($model);
                    $processed++;
                } catch (\Throwable $e) {
                    $errors++;
                    $this->newLine();
                    $this->warn("Error processing {$model->getKey()}: {$e->getMessage()}");
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Processed: {$processed}");
        if ($errors > 0) {
            $this->warn("Errors: {$errors}");
        }

        return self::SUCCESS;
    }
}
