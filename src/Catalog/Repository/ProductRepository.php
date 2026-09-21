<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\Domain\ProductFilters;
use App\Catalog\Domain\Product;
use App\Catalog\Read\ProductReadSource;

interface ProductRepository extends ProductReadSource
{
    /** @return list<Product> */
    public function all(): array;

    /** @return list<Product> */
    public function search(ProductFilters $filters): array;

    public function find(int $id): ?Product;

    /** @param list<int> $categoryIds */
    public function create(string $name, string $inn, string $ean13, string $description, array $categoryIds): Product;

    /** @param list<int> $categoryIds */
    public function update(int $id, string $name, string $inn, string $ean13, string $description, array $categoryIds): Product;

    public function delete(int $id): void;

    public function existsByIdentity(string $inn, string $ean13, ?int $exceptId = null): bool;
}
