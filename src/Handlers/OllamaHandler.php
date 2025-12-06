<?php

declare(strict_types=1);

namespace Vlados\LaravelRelatedContent\Handlers;

use GuzzleHttp\Client;
use Pgvector\Laravel\Vector;
use RuntimeException;
use Vlados\LaravelRelatedContent\Contracts\EmbeddingProvider;

class OllamaHandler implements EmbeddingProvider
{
    protected Client $client;

    protected string $model;

    protected int $dimensions;

    protected string $baseUrl;

    public function __construct(array $config)
    {
        $this->baseUrl = rtrim($config['base_url'] ?? 'http://localhost:11434', '/');
        $this->model = $config['model'] ?? 'nomic-embed-text';
        $this->dimensions = (int) ($config['dimensions'] ?? 768);

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 120,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function generate(string $text): Vector
    {
        $response = $this->client->post('/api/embeddings', [
            'json' => [
                'model' => $this->model,
                'prompt' => $text,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (! isset($data['embedding'])) {
            throw new RuntimeException('Invalid response from Ollama embeddings API');
        }

        return new Vector($data['embedding']);
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function model(): string
    {
        return $this->model;
    }
}
