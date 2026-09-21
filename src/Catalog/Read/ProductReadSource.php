<?php

declare(strict_types=1);

namespace App\Catalog\Read;

use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductFilters;

interface ProductReadSource
{
    /** @return list<Product> */
    public function search(ProductFilters $filters): array;

    public function find(int $id): ?Product;
}
