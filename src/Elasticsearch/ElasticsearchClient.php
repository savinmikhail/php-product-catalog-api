<?php

declare(strict_types=1);

namespace App\Elasticsearch;

use App\Shared\Exception\IndexingException;

final class ElasticsearchClient
{
    public function __construct(
        private readonly ElasticsearchTransport $transport,
        private readonly string $index,
    ) {
    }

    /** @param array<string, mixed> $mapping */
    public function createIndex(array $mapping): void
    {
        $response = $this->transport->request('PUT', $this->indexPath(), ['mappings' => $mapping]);
        if ($response->isSuccessful() || $this->isAlreadyExistingIndex($response)) {
            return;
        }

        throw new IndexingException('Elasticsearch index could not be created');
    }

    public function deleteIndex(): void
    {
        $response = $this->transport->request('DELETE', $this->indexPath());
        if ($response->isSuccessful() || $response->statusCode === 404) {
            return;
        }

        throw new IndexingException('Elasticsearch index could not be deleted');
    }

    /** @param array<string, mixed> $document */
    public function indexDocument(int $documentId, array $document): void
    {
        $response = $this->transport->request('PUT', $this->documentPath($documentId), $document);
        if ($response->isSuccessful()) {
            return;
        }

        throw new IndexingException('Product could not be indexed in Elasticsearch');
    }

    public function deleteDocument(int $documentId): void
    {
        $response = $this->transport->request('DELETE', $this->documentPath($documentId));
        if ($response->isSuccessful() || $response->statusCode === 404) {
            return;
        }

        throw new IndexingException('Product could not be deleted from Elasticsearch');
    }

    /** @param array<string, mixed> $query @return array<string, mixed> */
    public function search(array $query): array
    {
        $response = $this->transport->request('POST', $this->indexPath() . '/_search', $query);
        if (!$response->isSuccessful()) {
            throw new IndexingException('Elasticsearch product search failed');
        }

        return $response->body;
    }

    /** @return array<string, mixed>|null */
    public function findDocument(int $documentId): ?array
    {
        $response = $this->transport->request('GET', $this->documentPath($documentId));
        if ($response->statusCode === 404 || ($response->body['found'] ?? null) === false) {
            return null;
        }
        if (!$response->isSuccessful() || !is_array($response->body['_source'] ?? null)) {
            throw new IndexingException('Elasticsearch product lookup failed');
        }

        return $response->body['_source'];
    }

    private function indexPath(): string
    {
        return '/' . rawurlencode($this->index);
    }

    private function documentPath(int $documentId): string
    {
        return $this->indexPath() . '/_doc/' . $documentId;
    }

    private function isAlreadyExistingIndex(ElasticsearchResponse $response): bool
    {
        return $response->statusCode === 400
            && ($response->body['error']['type'] ?? null) === 'resource_already_exists_exception';
    }
}
