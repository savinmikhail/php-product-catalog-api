<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Port;

use App\Modules\Catalog\Domain\Product;

interface ProductIndexer
{
    public function createIndex(): void;

    public function recreateIndex(): void;

    public function index(Product $product): void;

    public function delete(int $productId): void;
}
