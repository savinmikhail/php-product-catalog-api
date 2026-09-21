<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\Domain\Category;

interface CategoryRepository
{
    /** @return list<Category> */
    public function all(): array;

    public function find(int $id): ?Category;

    public function create(string $name): Category;

    public function update(int $id, string $name): Category;

    public function delete(int $id): void;

    public function exists(int $id): bool;

    public function existsByName(string $name, ?int $exceptId = null): bool;
}
