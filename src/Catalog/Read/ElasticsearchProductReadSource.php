<?php

declare(strict_types=1);

namespace App\Catalog\Read;

use App\Catalog\Domain\Category;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductFilters;
use App\Elasticsearch\ElasticsearchClient;
use App\Shared\Exception\IndexingException;

final class ElasticsearchProductReadSource implements ProductReadSource
{
    public function __construct(private readonly ElasticsearchClient $client)
    {
    }

    public function search(ProductFilters $filters): array
    {
        $body = $this->client->search($this->query($filters));
        $hits = $body['hits']['hits'] ?? null;
        if (!is_array($hits)) {
            throw new IndexingException('Elasticsearch search returned an invalid response');
        }

        $products = [];
        foreach ($hits as $hit) {
            if (!is_array($hit) || !is_array($hit['_source'] ?? null)) {
                throw new IndexingException('Elasticsearch product document is stale');
            }
            $products[] = $this->product($hit['_source']);
        }

        return $products;
    }

    public function find(int $id): ?Product
    {
        $document = $this->client->findDocument($id);
        if ($document === null) {
            return null;
        }

        return $this->product($document);
    }

    /** @return array<string, mixed> */
    private function query(ProductFilters $filters): array
    {
        $filter = [];
        if ($filters->name !== null) {
            $filter[] = [
                'wildcard' => [
                    'name.keyword' => [
                        'value' => '*' . $this->wildcardValue($filters->name) . '*',
                        'case_insensitive' => true,
                    ],
                ],
            ];
        }
        if ($filters->inn !== null) {
            $filter[] = ['term' => ['inn' => $filters->inn]];
        }
        if ($filters->ean13 !== null) {
            $filter[] = ['term' => ['ean13' => $filters->ean13]];
        }
        if ($filters->categoryId !== null) {
            $filter[] = [
                'nested' => [
                    'path' => 'categories',
                    'query' => ['term' => ['categories.id' => $filters->categoryId]],
                ],
            ];
        }

        return [
            'query' => $filter === [] ? ['match_all' => (object) []] : ['bool' => ['filter' => $filter]],
            'sort' => [['id' => 'asc']],
            // The existing endpoint has no pagination and MySQL returns the complete result set.
            'size' => 10000,
        ];
    }

    private function wildcardValue(string $value): string
    {
        return strtr($value, ['\\' => '\\\\', '*' => '\\*', '?' => '\\?']);
    }

    /** @param array<string, mixed> $document */
    private function product(array $document): Product
    {
        foreach (['id', 'name', 'inn', 'ean13', 'description', 'categories'] as $field) {
            if (!array_key_exists($field, $document)) {
                throw new IndexingException('Elasticsearch product document is stale');
            }
        }
        if ((!is_int($document['id']) && !(is_string($document['id']) && ctype_digit($document['id'])))
            || !is_string($document['name'])
            || !is_string($document['inn'])
            || !is_string($document['ean13'])
            || !is_string($document['description'])
            || !is_array($document['categories'])) {
            throw new IndexingException('Elasticsearch product document is stale');
        }

        $categories = [];
        foreach ($document['categories'] as $category) {
            if (!is_array($category)
                || (!is_int($category['id'] ?? null) && !(is_string($category['id'] ?? null) && ctype_digit($category['id'])))
                || !is_string($category['name'] ?? null)) {
                throw new IndexingException('Elasticsearch product document is stale');
            }
            $categories[] = new Category((int) $category['id'], $category['name']);
        }

        return new Product(
            (int) $document['id'],
            $document['name'],
            $document['inn'],
            $document['ean13'],
            $document['description'],
            $categories,
        );
    }
}
