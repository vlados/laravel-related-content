<?php

declare(strict_types=1);

namespace Vlados\LaravelRelatedContent\Handlers;

use GuzzleHttp\Client;
use Pgvector\Laravel\Vector;
use RuntimeException;
use Vlados\LaravelRelatedContent\Contracts\EmbeddingProvider;

class OpenAiHandler implements EmbeddingProvider
{
    protected Client $client;

    protected string $model;

    protected int $dimensions;

    protected string $apiKey;

    protected string $baseUrl;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? throw new RuntimeException('OpenAI API key is required');
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://api.openai.com/v1', '/').'/';
        $this->model = $config['model'] ?? 'text-embedding-3-small';
        $this->dimensions = (int) ($config['dimensions'] ?? 1536);

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 60,
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function generate(string $text): Vector
    {
        $response = $this->client->post('embeddings', [
            'json' => [
                'model' => $this->model,
                'input' => $text,
                'dimensions' => $this->dimensions,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (! isset($data['data'][0]['embedding'])) {
            throw new RuntimeException('Invalid response from OpenAI embeddings API');
        }

        return new Vector($data['data'][0]['embedding']);
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
