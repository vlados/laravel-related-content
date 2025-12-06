<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Pgvector\Laravel\Vector;
use Vlados\LaravelRelatedContent\Handlers\OllamaHandler;
use Vlados\LaravelRelatedContent\Handlers\OpenAiHandler;

describe('OpenAiHandler', function () {
    it('throws exception when API key is missing', function () {
        new OpenAiHandler([
            'model' => 'text-embedding-3-small',
            'dimensions' => 1536,
        ]);
    })->throws(RuntimeException::class, 'OpenAI API key is required');

    it('returns correct model name', function () {
        $handler = new OpenAiHandler([
            'api_key' => 'test-key',
            'model' => 'text-embedding-3-small',
            'dimensions' => 1536,
        ]);

        expect($handler->model())->toBe('text-embedding-3-small');
    });

    it('returns correct dimensions', function () {
        $handler = new OpenAiHandler([
            'api_key' => 'test-key',
            'model' => 'text-embedding-3-small',
            'dimensions' => 1536,
        ]);

        expect($handler->dimensions())->toBe(1536);
    });

    it('uses default values when not provided', function () {
        $handler = new OpenAiHandler([
            'api_key' => 'test-key',
        ]);

        expect($handler->model())->toBe('text-embedding-3-small')
            ->and($handler->dimensions())->toBe(1536);
    });

    it('generates embedding from API response', function () {
        $fakeEmbedding = generateFakeEmbedding(1536);

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    ['embedding' => $fakeEmbedding],
                ],
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $handler = new OpenAiHandler([
            'api_key' => 'test-key',
            'model' => 'text-embedding-3-small',
            'dimensions' => 1536,
        ]);

        // Use reflection to inject mock client
        $reflection = new ReflectionClass($handler);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($handler, $client);

        $result = $handler->generate('Test text');

        expect($result)->toBeInstanceOf(Vector::class)
            ->and($result->toArray())->toBe($fakeEmbedding);
    });

    it('throws exception on invalid API response', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'error' => 'Something went wrong',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $handler = new OpenAiHandler([
            'api_key' => 'test-key',
        ]);

        $reflection = new ReflectionClass($handler);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($handler, $client);

        $handler->generate('Test text');
    })->throws(RuntimeException::class, 'Invalid response from OpenAI embeddings API');
});

describe('OllamaHandler', function () {
    it('returns correct model name', function () {
        $handler = new OllamaHandler([
            'model' => 'nomic-embed-text',
            'dimensions' => 768,
        ]);

        expect($handler->model())->toBe('nomic-embed-text');
    });

    it('returns correct dimensions', function () {
        $handler = new OllamaHandler([
            'model' => 'nomic-embed-text',
            'dimensions' => 768,
        ]);

        expect($handler->dimensions())->toBe(768);
    });

    it('uses default values when not provided', function () {
        $handler = new OllamaHandler([]);

        expect($handler->model())->toBe('nomic-embed-text')
            ->and($handler->dimensions())->toBe(768);
    });

    it('generates embedding from API response', function () {
        $fakeEmbedding = generateFakeEmbedding(768);

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'embedding' => $fakeEmbedding,
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $handler = new OllamaHandler([
            'model' => 'nomic-embed-text',
            'dimensions' => 768,
        ]);

        $reflection = new ReflectionClass($handler);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($handler, $client);

        $result = $handler->generate('Test text');

        expect($result)->toBeInstanceOf(Vector::class)
            ->and($result->toArray())->toBe($fakeEmbedding);
    });

    it('throws exception on invalid API response', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'error' => 'Model not found',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $handler = new OllamaHandler([]);

        $reflection = new ReflectionClass($handler);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($handler, $client);

        $handler->generate('Test text');
    })->throws(RuntimeException::class, 'Invalid response from Ollama embeddings API');
});
