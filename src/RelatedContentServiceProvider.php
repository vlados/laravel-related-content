<?php

declare(strict_types=1);

namespace Vlados\LaravelRelatedContent;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Vlados\LaravelRelatedContent\Commands\RebuildRelatedContentCommand;
use Vlados\LaravelRelatedContent\Contracts\EmbeddingProvider;
use Vlados\LaravelRelatedContent\Handlers\NullHandler;
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
            // Check if package is explicitly disabled
            if (! config('related-content.enabled', true)) {
                return $this->createNullHandler();
            }

            $provider = config('related-content.provider', 'openai');

            // Check if provider is properly configured
            if (! $this->isProviderConfigured($provider)) {
                return $this->createNullHandler();
            }

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

    /**
     * Check if the given provider has all required configuration.
     */
    protected function isProviderConfigured(string $provider): bool
    {
        return match ($provider) {
            'openai' => ! empty(config('related-content.providers.openai.api_key')),
            'ollama' => ! empty(config('related-content.providers.ollama.base_url')),
            default => false,
        };
    }

    /**
     * Create a NullHandler with the configured dimensions.
     */
    protected function createNullHandler(): NullHandler
    {
        $dimensions = (int) config('related-content.dimensions', 1536);

        return new NullHandler($dimensions);
    }
}
