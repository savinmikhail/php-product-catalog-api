<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Catalog\Domain\Product;
use App\Modules\Catalog\Domain\ProductFilters;
use App\Modules\Catalog\Infrastructure\Search\ProductReindexer;
use App\Modules\Catalog\Port\ProductRepository;
use PHPUnit\Framework\TestCase;

final class ProductReindexerTest extends TestCase
{
    public function testReindexRecreatesIndexAndIndexesEveryProductFromRepository(): void
    {
        $products = [
            new Product(1, 'First', '7701234567', '4601234567890', 'One'),
            new Product(2, 'Second', '7701234568', '4601234567891', 'Two'),
        ];
        $indexer = new FakeProductIndexer();
        $reindexer = new ProductReindexer(new FakeProductRepository($products), $indexer);

        self::assertSame(2, $reindexer->reindex());
        self::assertSame(1, $indexer->recreatedIndexes);
        self::assertSame($products, $indexer->indexed);
    }
}

final class FakeProductRepository implements ProductRepository
{
    /** @param list<Product> $products */
    public function __construct(private readonly array $products)
    {
    }

    public function all(): array
    {
        return $this->products;
    }

    public function search(ProductFilters $filters): array
    {
        return $this->products;
    }

    public function find(int $id): ?Product
    {
        return null;
    }

    public function create(string $name, string $inn, string $ean13, string $description, array $categoryIds): Product
    {
        throw new \LogicException('Not used in reindex test');
    }

    public function update(int $id, string $name, string $inn, string $ean13, string $description, array $categoryIds): Product
    {
        throw new \LogicException('Not used in reindex test');
    }

    public function delete(int $id): void
    {
        throw new \LogicException('Not used in reindex test');
    }

    public function existsByIdentity(string $inn, string $ean13, ?int $exceptId = null): bool
    {
        throw new \LogicException('Not used in reindex test');
    }
}
