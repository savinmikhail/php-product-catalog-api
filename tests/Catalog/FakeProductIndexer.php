<?php

declare(strict_types=1);

namespace Tests\Catalog;

use App\Catalog\Domain\Product;
use App\Catalog\Indexing\ProductIndexer;
use App\Shared\Exception\IndexingException;

final class FakeProductIndexer implements ProductIndexer
{
    /** @var list<Product> */
    public array $indexed = [];
    /** @var list<int> */
    public array $deleted = [];
    public int $createdIndexes = 0;
    public int $recreatedIndexes = 0;
    public bool $fail = false;

    public function createIndex(): void
    {
        $this->createdIndexes++;
    }

    public function recreateIndex(): void
    {
        $this->recreatedIndexes++;
    }

    public function index(Product $product): void
    {
        if ($this->fail) {
            throw new IndexingException();
        }

        $this->indexed[] = $product;
    }

    public function delete(int $productId): void
    {
        if ($this->fail) {
            throw new IndexingException();
        }

        $this->deleted[] = $productId;
    }
}
