<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Search;

use App\Modules\Catalog\Port\ProductRepository;
use App\Modules\Catalog\Port\ProductIndexer;

final class ProductReindexer
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductIndexer $indexer,
    ) {
    }

    public function reindex(): int
    {
        $this->indexer->recreateIndex();
        $count = 0;
        foreach ($this->products->all() as $product) {
            $this->indexer->index($product);
            $count++;
        }

        return $count;
    }
}
