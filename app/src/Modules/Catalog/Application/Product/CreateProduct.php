<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Product;

use App\Modules\Catalog\Port\CategoryRepository;
use App\Modules\Catalog\Port\ProductIndexer;
use App\Modules\Catalog\Port\ProductRepository;
use App\Modules\InnValidation\Port\InnValidator;
use App\Shared\Error\ConflictException;
use App\Shared\Error\ValidationException;

final class CreateProduct
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly CategoryRepository $categories,
        private readonly ProductIndexer $indexer,
        private readonly InnValidator $innValidator,
        private readonly ProductInputValidator $input,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function __invoke(array $data): \App\Modules\Catalog\Domain\Product
    {
        $product = $this->input->forCreate($data);
        $this->innValidator->validate($product->inn);
        $this->ensureIdentityIsAvailable($product->inn, $product->ean13);
        $this->ensureCategoriesExist($product->categoryIds);

        $created = $this->products->create(
            $product->name,
            $product->inn,
            $product->ean13,
            $product->description,
            $product->categoryIds,
        );
        $this->indexer->index($created);

        return $created;
    }

    /** @param list<int> $categoryIds */
    private function ensureCategoriesExist(array $categoryIds): void
    {
        $missing = array_values(array_filter($categoryIds, fn (int $id): bool => !$this->categories->exists($id)));
        if ($missing !== []) {
            throw new ValidationException(['category_ids' => 'Category does not exist: ' . implode(', ', $missing)]);
        }
    }

    private function ensureIdentityIsAvailable(string $inn, string $ean13): void
    {
        if ($this->products->existsByIdentity($inn, $ean13)) {
            throw new ConflictException('Product with this INN and EAN-13 already exists');
        }
    }
}
