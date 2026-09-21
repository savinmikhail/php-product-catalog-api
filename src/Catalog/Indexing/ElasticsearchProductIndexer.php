<?php

declare(strict_types=1);

namespace App\Catalog\Indexing;

use App\Catalog\Domain\Product;
use App\Elasticsearch\ElasticsearchClient;

final class ElasticsearchProductIndexer implements ProductIndexer
{
    public function __construct(private readonly ElasticsearchClient $client)
    {
    }

    public function createIndex(): void
    {
        $this->client->createIndex([
            'properties' => [
                'id' => ['type' => 'long'],
                'name' => [
                    'type' => 'text',
                    'fields' => ['keyword' => ['type' => 'keyword']],
                ],
                'inn' => ['type' => 'keyword'],
                'ean13' => ['type' => 'keyword'],
                'description' => ['type' => 'text'],
                'categories' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => ['type' => 'long'],
                        'name' => [
                            'type' => 'text',
                            'fields' => ['keyword' => ['type' => 'keyword']],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function recreateIndex(): void
    {
        $this->client->deleteIndex();
        $this->createIndex();
    }

    public function index(Product $product): void
    {
        $this->client->indexDocument($product->id, [
            'id' => $product->id,
            'name' => $product->name,
            'inn' => $product->inn,
            'ean13' => $product->ean13,
            'description' => $product->description,
            'categories' => array_map(
                static fn ($category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                ],
                $product->categories,
            ),
        ]);
    }

    public function delete(int $productId): void
    {
        $this->client->deleteDocument($productId);
    }
}
