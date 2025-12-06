# Laravel Related Content

Build related content links using vector embeddings and pgvector for Laravel.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vlados/laravel-related-content.svg?style=flat-square)](https://packagist.org/packages/vlados/laravel-related-content)

## Features

- 🔗 **Pre-computed Related Links** - Related content is calculated on save, not on every page load
- 🚀 **Fast Lookups** - O(1) relationship queries instead of real-time similarity search
- 🔄 **Cross-Model Relationships** - Find related content across different model types (Blog → Events → Questions)
- 🧠 **Multiple Embedding Providers** - Support for OpenAI and Ollama
- 📦 **Queue Support** - Process embeddings in the background
- 🔍 **Semantic Search** - Search content by meaning, not just keywords

## Requirements

- PHP 8.3+
- Laravel 11 or 12
- PostgreSQL with pgvector extension

## Installation

### 1. Install pgvector extension in PostgreSQL

```sql
CREATE EXTENSION IF NOT EXISTS vector;
```

### 2. Install the package via Composer

```bash
composer require vlados/laravel-related-content
```

### 3. Publish the config and migrations

```bash
php artisan vendor:publish --tag="related-content-config"
php artisan vendor:publish --tag="related-content-migrations"
php artisan migrate
```

### 4. Configure your environment

```env
# Embedding provider (openai or ollama)
RELATED_CONTENT_PROVIDER=openai

# OpenAI settings
OPENAI_API_KEY=your-api-key
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
OPENAI_EMBEDDING_DIMENSIONS=1536

# Or Ollama settings
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_EMBEDDING_MODEL=nomic-embed-text
```

## Usage

### 1. Add the trait to your models

```php
use Vlados\LaravelRelatedContent\Concerns\HasRelatedContent;

class BlogPost extends Model
{
    use HasRelatedContent;

    /**
     * Define which fields should be embedded.
     */
    public function embeddableFields(): array
    {
        return ['title', 'excerpt', 'content'];
    }
}
```

### 2. Configure models for cross-model relationships

In `config/related-content.php`:

```php
'models' => [
    \App\Models\BlogPost::class,
    \App\Models\Event::class,
    \App\Models\Question::class,
],
```

### 3. Related content is automatically synced on save

```php
$post = BlogPost::create([
    'title' => 'Electric Vehicle Charging Guide',
    'content' => '...',
]);

// Embedding is generated and related content is found automatically
```

### 4. Retrieve related content

```php
// Get all related content
$related = $post->getRelatedModels();

// Get related content of a specific type
$relatedEvents = $post->getRelatedOfType(Event::class);

// Get the raw relationship with similarity scores
$post->relatedContent()->with('related')->get();
```

### 5. Use in Blade templates

```blade
@if($post->relatedContent->isNotEmpty())
    <div class="related-content">
        <h3>Related Content</h3>
        @foreach($post->getRelatedModels(5) as $item)
            <a href="{{ $item->url }}">{{ $item->title }}</a>
        @endforeach
    </div>
@endif
```

## Artisan Commands

### Generate embeddings for existing content

```bash
# Generate embeddings for all configured models
php artisan related-content:embeddings

# Generate embeddings for a specific model
php artisan related-content:embeddings "App\Models\BlogPost"

# With chunk size
php artisan related-content:embeddings "App\Models\BlogPost" --chunk=50
```

### Rebuild related content links

```bash
# Rebuild for a specific model
php artisan related-content:rebuild "App\Models\BlogPost"

# Rebuild for all configured models
php artisan related-content:rebuild

# Process synchronously (instead of queuing)
php artisan related-content:rebuild --sync
```

## Semantic Search

You can also use the package for semantic search:

```php
use Vlados\LaravelRelatedContent\Services\RelatedContentService;

$service = app(RelatedContentService::class);

// Search across all embeddable models
$results = $service->search('electric vehicle charging');

// Search specific model types
$results = $service->search('charging stations', [
    \App\Models\Event::class,
    \App\Models\BlogPost::class,
]);
```

## Configuration

```php
return [
    // Embedding provider: 'openai' or 'ollama'
    'provider' => env('RELATED_CONTENT_PROVIDER', 'openai'),

    // Provider-specific settings
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'dimensions' => env('OPENAI_EMBEDDING_DIMENSIONS', 1536),
        ],
        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
            'model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
            'dimensions' => env('OLLAMA_EMBEDDING_DIMENSIONS', 768),
        ],
    ],

    // Maximum related items per model
    'max_related_items' => 10,

    // Minimum similarity threshold (0-1)
    'similarity_threshold' => 0.5,

    // Queue settings
    'queue' => [
        'connection' => 'default',
        'name' => 'default',
    ],

    // Models to include in cross-model relationships
    'models' => [],

    // Database table names
    'tables' => [
        'embeddings' => 'embeddings',
        'related_content' => 'related_content',
    ],
];
```

## Events

The package dispatches events you can listen to:

```php
use Vlados\LaravelRelatedContent\Events\RelatedContentSynced;

class HandleRelatedContentSynced
{
    public function handle(RelatedContentSynced $event): void
    {
        // $event->model - The model that was synced
    }
}
```

## How It Works

1. **On Model Save**: When a model with `HasRelatedContent` is saved, a job is dispatched
2. **Generate Embedding**: The job generates a vector embedding from the model's embeddable fields
3. **Find Similar**: Uses pgvector to find similar content across all configured models
4. **Store Links**: Stores the related content relationships in the `related_content` table
5. **Fast Retrieval**: When displaying related content, it's a simple database lookup (no API calls)

### Bidirectional Relationships

Related content works in both directions automatically. When a new BlogPost is saved and finds an Event as related, the Event will also show the BlogPost in its related content - without needing to re-sync the Event.

This is achieved by querying both directions:
- Forward: where this model is the source
- Reverse: where this model is the related target

Results are deduplicated and sorted by similarity score.

## Performance

- **Embedding Generation**: ~200-500ms per model (depends on text length and provider)
- **Related Content Lookup**: ~5ms (simple database query)
- **Storage**: ~6KB per embedding (1536 dimensions x 4 bytes)

## License

MIT License. See [LICENSE](LICENSE.md) for more information.

## Credits

- [Vladislav Stoitsov](https://github.com/vlados)
