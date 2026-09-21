<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Product;

use App\Modules\Catalog\Domain\Product;
use App\Modules\Catalog\Port\CategoryRepository;
use App\Modules\Catalog\Port\ProductIndexer;
use App\Modules\Catalog\Port\ProductRepository;
use App\Modules\Catalog\Application\Product\GetProduct;
use App\Modules\InnValidation\Port\InnValidator;
use App\Shared\Error\ConflictException;
use App\Shared\Error\ValidationException;

final class UpdateProduct
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly CategoryRepository $categories,
        private readonly ProductIndexer $indexer,
        private readonly InnValidator $innValidator,
        private readonly ProductInputValidator $input,
        private readonly GetProduct $getProduct,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function __invoke(int $id, array $data): Product
    {
        $current = ($this->getProduct)($id);
        $product = $this->input->forUpdate($data, $current);
        $this->innValidator->validate($product->inn);
        $this->ensureIdentityIsAvailable($product->inn, $product->ean13, $id);
        $this->ensureCategoriesExist($product->categoryIds);

        $updated = $this->products->update(
            $id,
            $product->name,
            $product->inn,
            $product->ean13,
            $product->description,
            $product->categoryIds,
        );
        $this->indexer->index($updated);

        return $updated;
    }

    /** @param list<int> $categoryIds */
    private function ensureCategoriesExist(array $categoryIds): void
    {
        $missing = array_values(array_filter($categoryIds, fn (int $categoryId): bool => !$this->categories->exists($categoryId)));
        if ($missing !== []) {
            throw new ValidationException(['category_ids' => 'Category does not exist: ' . implode(', ', $missing)]);
        }
    }

    private function ensureIdentityIsAvailable(string $inn, string $ean13, int $exceptId): void
    {
        if ($this->products->existsByIdentity($inn, $ean13, $exceptId)) {
            throw new ConflictException('Product with this INN and EAN-13 already exists');
        }
    }
}
