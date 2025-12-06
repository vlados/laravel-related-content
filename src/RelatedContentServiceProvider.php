<?php

declare(strict_types=1);

namespace Vlados\LaravelRelatedContent;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Vlados\LaravelRelatedContent\Commands\RebuildRelatedContentCommand;
use Vlados\LaravelRelatedContent\Contracts\EmbeddingProvider;
use Vlados\LaravelRelatedContent\Handlers\OllamaHandler;
use Vlados\LaravelRelatedContent\Handlers\OpenAiHandler;
use Vlados\LaravelRelatedContent\Services\EmbeddingService;
use Vlados\LaravelRelatedContent\Services\RelatedContentService;

class RelatedContentServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-related-content')
            ->hasConfigFile('related-content')
            ->hasMigrations([
                'create_embeddings_table',
                'create_related_content_table',
            ])
            ->hasCommands([
                RebuildRelatedContentCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Register the embedding provider based on config
        $this->app->singleton(EmbeddingProvider::class, function ($app) {
            $provider = config('related-content.provider', 'openai');

            return match ($provider) {
                'ollama' => new OllamaHandler(config('related-content.providers.ollama')),
                default => new OpenAiHandler(config('related-content.providers.openai')),
            };
        });

        // Register the embedding service
        $this->app->singleton(EmbeddingService::class, function ($app) {
            return new EmbeddingService($app->make(EmbeddingProvider::class));
        });

        // Register the related content service
        $this->app->singleton(RelatedContentService::class, function ($app) {
            return new RelatedContentService($app->make(EmbeddingService::class));
        });
    }
}
