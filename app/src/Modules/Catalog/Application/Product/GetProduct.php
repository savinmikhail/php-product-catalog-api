<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Product;

use App\Modules\Catalog\Domain\Product;
use App\Modules\Catalog\Port\ProductReadSource;
use App\Shared\Error\NotFoundException;

final class GetProduct
{
    public function __construct(private readonly ProductReadSource $readSource)
    {
    }

    public function __invoke(int $id): Product
    {
        return $this->readSource->find($id) ?? throw new NotFoundException('Product not found');
    }
}
