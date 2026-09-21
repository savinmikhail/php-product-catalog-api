<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

final readonly class ProductFilters
{
    public function __construct(
        public ?string $name = null,
        public ?string $inn = null,
        public ?string $ean13 = null,
        public ?int $categoryId = null,
    ) {
    }
}
