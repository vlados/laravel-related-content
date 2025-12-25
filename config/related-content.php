<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Related Content
    |--------------------------------------------------------------------------
    |
    | When disabled, the package will use a NullHandler that returns zero
    | vectors. This is useful for testing environments or when you want
    | to temporarily disable embedding generation.
    |
    | The package will also automatically disable itself if the configured
    | provider is missing required credentials (e.g., OpenAI API key).
    |
    */
    'enabled' => env('RELATED_CONTENT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Embedding Provider
    |--------------------------------------------------------------------------
    |
    | The embedding provider to use for generating vector embeddings.
    | Supported: "openai", "ollama"
    |
    */
    'provider' => env('RELATED_CONTENT_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    */
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

    /*
    |--------------------------------------------------------------------------
    | Embedding Dimensions
    |--------------------------------------------------------------------------
    |
    | The number of dimensions for the vector embeddings. This should match
    | the output dimensions of your chosen embedding model.
    |
    | Common values:
    | - text-embedding-3-small: 1536 (can be reduced to 256, 512, etc.)
    | - text-embedding-3-large: 3072
    | - nomic-embed-text: 768
    |
    */
    'dimensions' => env('RELATED_CONTENT_DIMENSIONS', 1536),

    /*
    |--------------------------------------------------------------------------
    | Related Content Limits
    |--------------------------------------------------------------------------
    |
    | Maximum number of related items to store per content item.
    |
    */
    'max_related_items' => env('RELATED_CONTENT_MAX_ITEMS', 10),

    /*
    |--------------------------------------------------------------------------
    | Similarity Threshold
    |--------------------------------------------------------------------------
    |
    | Minimum similarity score (0-1) for content to be considered related.
    | Higher values = more strict matching.
    |
    */
    'similarity_threshold' => env('RELATED_CONTENT_SIMILARITY_THRESHOLD', 0.5),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Queue settings for background processing of embeddings.
    |
    */
    'queue' => [
        'connection' => env('RELATED_CONTENT_QUEUE_CONNECTION', 'default'),
        'name' => env('RELATED_CONTENT_QUEUE_NAME', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Types for Cross-Model Relationships
    |--------------------------------------------------------------------------
    |
    | List of model classes that should be considered for cross-model
    | related content. When generating related links for a model, these
    | models will be searched for similar content.
    |
    | Example:
    | 'models' => [
    |     \App\Models\BlogPost::class,
    |     \App\Models\Event::class,
    |     \App\Models\Question::class,
    | ],
    |
    */
    'models' => [],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Customize the database table names used by the package.
    |
    */
    'tables' => [
        'embeddings' => 'embeddings',
        'related_content' => 'related_content',
    ],
];
