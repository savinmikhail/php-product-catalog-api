<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

final readonly class Product
{
    /** @param list<Category> $categories */
    public function __construct(
        public int $id,
        public string $name,
        public string $inn,
        public string $ean13,
        public string $description,
        public array $categories = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'inn' => $this->inn,
            'ean13' => $this->ean13,
            'description' => $this->description,
            'categories' => array_map(static fn (Category $category): array => $category->toArray(), $this->categories),
        ];
    }
}
