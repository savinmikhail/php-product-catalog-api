<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Port;

use App\Modules\Catalog\Domain\Product;
use App\Modules\Catalog\Domain\ProductFilters;

interface ProductReadSource
{
    /** @return list<Product> */
    public function search(ProductFilters $filters): array;

    public function find(int $id): ?Product;
}
