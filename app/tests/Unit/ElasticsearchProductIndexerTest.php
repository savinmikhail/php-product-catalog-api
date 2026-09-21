<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Catalog\Domain\Category;
use App\Modules\Catalog\Domain\Product;
use App\Modules\Catalog\Infrastructure\Search\ElasticsearchProductIndexer;
use App\Modules\Catalog\Infrastructure\Search\ElasticsearchClient;
use App\Modules\Catalog\Infrastructure\Search\ElasticsearchResponse;
use App\Modules\Catalog\Infrastructure\Search\ElasticsearchTransport;
use PHPUnit\Framework\TestCase;

final class ElasticsearchProductIndexerTest extends TestCase
{
    public function testProductMappingAndLifecycleUseElasticsearchTransport(): void
    {
        $transport = new FakeElasticsearchTransport([
            new ElasticsearchResponse(200),
            new ElasticsearchResponse(201),
            new ElasticsearchResponse(200),
            new ElasticsearchResponse(200),
        ]);
        $indexer = new ElasticsearchProductIndexer(new ElasticsearchClient($transport, 'products'));
        $product = new Product(
            7,
            'API Handbook',
            '7701234567',
            '4601234567890',
            'A practical guide',
            [new Category(3, 'Books')],
        );

        $indexer->createIndex();
        $indexer->index($product);
        $indexer->delete(7);
        $indexer->recreateIndex();

        self::assertSame(['PUT', '/products'], [$transport->requests[0]['method'], $transport->requests[0]['path']]);
        self::assertSame('nested', $transport->requests[0]['body']['mappings']['properties']['categories']['type']);
        self::assertSame(['PUT', '/products/_doc/7'], [$transport->requests[1]['method'], $transport->requests[1]['path']]);
        self::assertSame('7701234567', $transport->requests[1]['body']['inn']);
        self::assertSame([['id' => 3, 'name' => 'Books']], $transport->requests[1]['body']['categories']);
        self::assertSame(['DELETE', '/products/_doc/7'], [$transport->requests[2]['method'], $transport->requests[2]['path']]);
        self::assertSame(['DELETE', '/products'], [$transport->requests[3]['method'], $transport->requests[3]['path']]);
        self::assertSame(['PUT', '/products'], [$transport->requests[4]['method'], $transport->requests[4]['path']]);
    }
}

final class FakeElasticsearchTransport implements ElasticsearchTransport
{
    /** @var list<array{method:string,path:string,body:?array<string,mixed>}> */
    public array $requests = [];

    /** @param list<ElasticsearchResponse> $responses */
    public function __construct(private array $responses)
    {
    }

    public function request(string $method, string $path, ?array $body = null): ElasticsearchResponse
    {
        $this->requests[] = ['method' => $method, 'path' => $path, 'body' => $body];

        return array_shift($this->responses) ?? new ElasticsearchResponse(200);
    }
}
