<?php

declare(strict_types=1);

namespace App\Catalog\Read;

use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductFilters;
use Throwable;

/**
 * Elasticsearch is preferred; failed, empty, missing, or malformed reads use MySQL.
 * The current index has no version/timestamp, so a structurally valid old document is served as-is.
 */
final class FallbackProductReadSource implements ProductReadSource
{
    public function __construct(
        private readonly ProductReadSource $primary,
        private readonly ProductReadSource $fallback,
    ) {
    }

    public function search(ProductFilters $filters): array
    {
        try {
            $products = $this->primary->search($filters);
            if ($products !== []) {
                return $products;
            }
        } catch (Throwable) {
            // A failed or stale Elasticsearch result is served by MySQL.
        }

        return $this->fallback->search($filters);
    }

    public function find(int $id): ?Product
    {
        try {
            $product = $this->primary->find($id);
            if ($product !== null) {
                return $product;
            }
        } catch (Throwable) {
            // A failed or missing Elasticsearch document is served by MySQL.
        }

        return $this->fallback->find($id);
    }
}
