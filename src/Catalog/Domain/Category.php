<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

final readonly class Category
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
