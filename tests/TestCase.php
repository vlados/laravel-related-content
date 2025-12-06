<?php

namespace Vlados\LaravelRelatedContent\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Vlados\LaravelRelatedContent\RelatedContentServiceProvider;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Vlados\\LaravelRelatedContent\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            RelatedContentServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Use SQLite for testing (pgvector features will be mocked)
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set up related content config
        config()->set('related-content.provider', 'openai');
        config()->set('related-content.dimensions', 1536);
        config()->set('related-content.max_related_items', 10);
        config()->set('related-content.similarity_threshold', 0.5);
        config()->set('related-content.providers.openai', [
            'api_key' => 'test-api-key',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'text-embedding-3-small',
            'dimensions' => 1536,
        ]);
    }
}
