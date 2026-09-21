<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Product;

final readonly class ProductInput
{
    /** @param list<int> $categoryIds */
    public function __construct(
        public string $name,
        public string $inn,
        public string $ean13,
        public string $description,
        public array $categoryIds,
    ) {
    }
}
