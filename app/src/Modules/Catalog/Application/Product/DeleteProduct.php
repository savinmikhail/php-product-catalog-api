<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Product;

use App\Modules\Catalog\Port\ProductIndexer;
use App\Modules\Catalog\Port\ProductRepository;

final class DeleteProduct
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductIndexer $indexer,
        private readonly GetProduct $getProduct,
    ) {
    }

    public function __invoke(int $id): void
    {
        ($this->getProduct)($id);
        $this->products->delete($id);
        $this->indexer->delete($id);
    }
}
