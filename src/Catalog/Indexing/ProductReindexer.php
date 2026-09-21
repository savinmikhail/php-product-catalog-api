<?php

declare(strict_types=1);

namespace App\Catalog\Indexing;

use App\Catalog\Repository\ProductRepository;

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
