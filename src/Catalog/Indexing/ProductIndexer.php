<?php

declare(strict_types=1);

namespace App\Catalog\Indexing;

use App\Catalog\Domain\Product;

interface ProductIndexer
{
    public function createIndex(): void;

    public function recreateIndex(): void;

    public function index(Product $product): void;

    public function delete(int $productId): void;
}
