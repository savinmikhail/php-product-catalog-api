<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Catalog\Domain\Category;
use App\Modules\Catalog\Domain\Product;
use App\Modules\Catalog\Domain\ProductFilters;
use App\Modules\Catalog\Infrastructure\Search\FallbackProductReadSource;
use App\Modules\Catalog\Infrastructure\Search\ElasticsearchProductReadSource;
use App\Modules\Catalog\Port\ProductReadSource;
use App\Modules\Catalog\Infrastructure\Search\ElasticsearchClient;
use App\Modules\Catalog\Infrastructure\Search\ElasticsearchResponse;
use App\Modules\Catalog\Infrastructure\Search\ElasticsearchTransport;
use App\Shared\Error\IndexingException;
use PHPUnit\Framework\TestCase;

final class ProductReadSourceTest extends TestCase
{
    public function testElasticsearchReadSourceBuildsFilterQueryAndMapsSearchHits(): void
    {
        $transport = new ReadTransport([
            new ElasticsearchResponse(200, [
                'hits' => [
                    'hits' => [[
                        '_source' => [
                            'id' => 7,
                            'name' => 'API Handbook',
                            'inn' => '7701234567',
                            'ean13' => '4601234567890',
                            'description' => 'A practical guide',
                            'categories' => [['id' => 3, 'name' => 'Books']],
                        ],
                    ]],
                ],
            ]),
        ]);

        $source = new \App\Modules\Catalog\Infrastructure\Search\ElasticsearchProductReadSource(
            new ElasticsearchClient($transport, 'products'),
        );

        $products = $source->search(new ProductFilters('hand', '7701234567', '4601234567890', 3));

        self::assertSame('API Handbook', $products[0]->name);
        self::assertSame([['id' => 3, 'name' => 'Books']], array_map(
            static fn (Category $category): array => $category->toArray(),
            $products[0]->categories,
        ));
        self::assertSame('POST', $transport->requests[0]['method']);
        self::assertSame('/products/_search', $transport->requests[0]['path']);
        self::assertSame(10000, $transport->requests[0]['body']['size']);
        self::assertSame(3, $transport->requests[0]['body']['query']['bool']['filter'][3]['nested']['query']['term']['categories.id']);
    }

    public function testElasticsearchReadSourceMapsMissingDocumentToNull(): void
    {
        $transport = new ReadTransport([new ElasticsearchResponse(404, ['found' => false])]);
        $source = new \App\Modules\Catalog\Infrastructure\Search\ElasticsearchProductReadSource(
            new ElasticsearchClient($transport, 'products'),
        );

        self::assertNull($source->find(7));
    }

    public function testFallbackUsesMySqlWhenElasticsearchFailsOrDocumentIsMissing(): void
    {
        $mysqlProduct = new Product(7, 'MySQL copy', '7701234567', '4601234567890', 'source');
        $primary = new ConfigurableReadSource(exception: new IndexingException('ES unavailable'));
        $fallback = new ConfigurableReadSource(products: [$mysqlProduct], product: $mysqlProduct);
        $source = new FallbackProductReadSource($primary, $fallback);

        self::assertSame([$mysqlProduct], $source->search(new ProductFilters()));

        $primary->exception = null;
        $primary->product = null;
        self::assertSame($mysqlProduct, $source->find(7));
    }

    public function testFallbackUsesMySqlForEmptyOrStaleElasticsearchSearchResults(): void
    {
        $mysqlProduct = new Product(7, 'MySQL copy', '7701234567', '4601234567890', 'source');
        $primary = new ConfigurableReadSource(products: []);
        $fallback = new ConfigurableReadSource(products: [$mysqlProduct]);
        $source = new FallbackProductReadSource($primary, $fallback);

        self::assertSame([$mysqlProduct], $source->search(new ProductFilters('copy')));

        $stalePrimary = new ElasticsearchProductReadSource(new ElasticsearchClient(new ReadTransport([
            new ElasticsearchResponse(200, ['hits' => ['hits' => [[
                '_source' => [
                    'id' => 7,
                    'name' => 'Stale ES copy',
                    'inn' => '7701234567',
                    'ean13' => '4601234567890',
                    // Missing description makes this document stale for the current contract.
                    'categories' => [],
                ],
            ]]]]),
        ]), 'products'));
        self::assertSame(
            [$mysqlProduct],
            (new FallbackProductReadSource($stalePrimary, $fallback))->search(new ProductFilters()),
        );
    }
}

final class ReadTransport implements ElasticsearchTransport
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

        return array_shift($this->responses) ?? throw new \LogicException('Unexpected Elasticsearch request');
    }
}

final class ConfigurableReadSource implements ProductReadSource
{
    /** @param list<Product> $products */
    public function __construct(
        public array $products = [],
        public ?Product $product = null,
        public ?\Throwable $exception = null,
    ) {
    }

    public function search(ProductFilters $filters): array
    {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->products;
    }

    public function find(int $id): ?Product
    {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->product;
    }
}
