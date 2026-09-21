<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Category;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductFilters;
use App\Catalog\Indexing\ProductIndexer;
use App\Catalog\Inn\InnValidationStrategy;
use App\Catalog\Read\ProductReadSource;
use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Repository\ProductRepository;
use App\Shared\Exception\ConflictException;
use App\Shared\Exception\NotFoundException;
use App\Shared\Exception\ValidationException;

final class CatalogService
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly CategoryRepository $categories,
        private readonly ProductIndexer $indexer,
        private readonly InnValidationStrategy $innValidation,
        private readonly ?ProductReadSource $readSource = null,
    ) {
    }

    /** @return list<Product> */
    public function products(array $query = []): array
    {
        return $this->readSource()->search($this->productFilters($query));
    }

    public function product(int $id): Product
    {
        return $this->readSource()->find($id) ?? throw new NotFoundException('Product not found');
    }

    public function createProduct(array $input): Product
    {
        $data = $this->validateProduct($input);
        $this->innValidation->validate($data['inn']);
        $this->ensureProductIdentityIsAvailable($data['inn'], $data['ean13']);
        $this->ensureCategoriesExist($data['category_ids']);

        $product = $this->products->create(
            $data['name'],
            $data['inn'],
            $data['ean13'],
            $data['description'],
            $data['category_ids'],
        );
        $this->indexer->index($product);

        return $product;
    }

    public function updateProduct(int $id, array $input): Product
    {
        $current = $this->product($id);
        $data = $this->validateProduct($input, $current);
        $this->innValidation->validate($data['inn']);
        $this->ensureProductIdentityIsAvailable($data['inn'], $data['ean13'], $id);
        $this->ensureCategoriesExist($data['category_ids']);

        $product = $this->products->update(
            $id,
            $data['name'],
            $data['inn'],
            $data['ean13'],
            $data['description'],
            $data['category_ids'],
        );
        $this->indexer->index($product);

        return $product;
    }

    public function deleteProduct(int $id): void
    {
        $this->product($id);
        $this->products->delete($id);
        $this->indexer->delete($id);
    }

    /** @return list<Category> */
    public function categories(): array
    {
        return $this->categories->all();
    }

    public function category(int $id): Category
    {
        return $this->categories->find($id) ?? throw new NotFoundException('Category not found');
    }

    public function createCategory(array $input): Category
    {
        $name = $this->validateCategory($input);
        if ($this->categories->existsByName($name)) {
            throw new ConflictException('Category name must be unique');
        }

        return $this->categories->create($name);
    }

    public function updateCategory(int $id, array $input): Category
    {
        $this->category($id);
        $name = $this->validateCategory($input);
        if ($this->categories->existsByName($name, $id)) {
            throw new ConflictException('Category name must be unique');
        }

        return $this->categories->update($id, $name);
    }

    public function deleteCategory(int $id): void
    {
        $this->category($id);
        $this->categories->delete($id);
    }

    /** @return array{name:string,inn:string,ean13:string,description:string,category_ids:list<int>} */
    private function validateProduct(array $input, ?Product $current = null): array
    {
        $errors = [];
        $name = $this->stringValue($input, 'name', $current?->name, $errors, true);
        $inn = $this->stringValue($input, 'inn', $current?->inn, $errors, true);
        $ean13 = $this->stringValue($input, 'ean13', $current?->ean13, $errors, true);
        $description = $this->stringValue($input, 'description', $current?->description, $errors, true, allowEmpty: true);

        if ($inn !== null && !preg_match('/^\d{10}$/', $inn)) {
            $errors['inn'] = 'INN must contain exactly 10 digits';
        }
        if ($ean13 !== null && !preg_match('/^\d{13}$/', $ean13)) {
            $errors['ean13'] = 'EAN-13 must contain exactly 13 digits';
        }

        $categoryIds = $input['category_ids'] ?? $this->categoryIds($current);
        if (!is_array($categoryIds) || array_is_list($categoryIds) === false) {
            $errors['category_ids'] = 'Category IDs must be an array';
            $categoryIds = [];
        }
        $normalizedCategoryIds = [];
        foreach ($categoryIds as $categoryId) {
            if (!is_int($categoryId) && !(is_string($categoryId) && ctype_digit($categoryId))) {
                $errors['category_ids'] = 'Category IDs must contain positive integers';
                continue;
            }
            $normalizedCategoryIds[] = (int) $categoryId;
        }
        if (count($normalizedCategoryIds) !== count(array_unique($normalizedCategoryIds))) {
            $errors['category_ids'] = 'Category IDs must be unique';
        }
        if (in_array(0, $normalizedCategoryIds, true)) {
            $errors['category_ids'] = 'Category IDs must contain positive integers';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'name' => $name ?? '',
            'inn' => $inn ?? '',
            'ean13' => $ean13 ?? '',
            'description' => $description ?? '',
            'category_ids' => $normalizedCategoryIds,
        ];
    }

    private function validateCategory(array $input): string
    {
        $errors = [];
        $name = $this->stringValue($input, 'name', null, $errors, true);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $name ?? '';
    }

    private function stringValue(
        array $input,
        string $field,
        ?string $fallback,
        array &$errors,
        bool $required,
        bool $allowEmpty = false,
    ): ?string {
        if (!array_key_exists($field, $input)) {
            if ($required && $fallback === null) {
                $errors[$field] = 'Field is required';
            }

            return $fallback;
        }
        if (!is_string($input[$field])) {
            $errors[$field] = 'Field must be a string';

            return null;
        }

        $value = trim($input[$field]);
        if (!$allowEmpty && $value === '') {
            $errors[$field] = 'Field must not be empty';
        }
        if (strlen($value) > 255 && $field !== 'description') {
            $errors[$field] = 'Field must not exceed 255 characters';
        }

        return $value;
    }

    /** @param list<int> $categoryIds */
    private function ensureCategoriesExist(array $categoryIds): void
    {
        $missing = array_values(array_filter($categoryIds, fn (int $id): bool => !$this->categories->exists($id)));
        if ($missing !== []) {
            throw new ValidationException(['category_ids' => 'Category does not exist: ' . implode(', ', $missing)]);
        }
    }

    private function ensureProductIdentityIsAvailable(string $inn, string $ean13, ?int $exceptId = null): void
    {
        if ($this->products->existsByIdentity($inn, $ean13, $exceptId)) {
            throw new ConflictException('Product with this INN and EAN-13 already exists');
        }
    }

    /** @return list<int> */
    private function categoryIds(?Product $product): array
    {
        return $product === null ? [] : array_map(static fn (Category $category): int => $category->id, $product->categories);
    }

    private function productFilters(array $query): ProductFilters
    {
        $errors = [];
        $allowed = ['name', 'inn', 'ean13', 'category'];
        foreach (array_keys($query) as $field) {
            if (!in_array($field, $allowed, true)) {
                $errors[$field] = 'Unknown filter';
            }
        }

        $name = $this->filterString($query, 'name', $errors);
        $inn = $this->filterString($query, 'inn', $errors);
        $ean13 = $this->filterString($query, 'ean13', $errors);
        $category = $this->filterCategory($query, $errors);

        if ($inn !== null && !preg_match('/^\d{10}$/', $inn)) {
            $errors['inn'] = 'INN must contain exactly 10 digits';
        }
        if ($ean13 !== null && !preg_match('/^\d{13}$/', $ean13)) {
            $errors['ean13'] = 'EAN-13 must contain exactly 13 digits';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new ProductFilters($name, $inn, $ean13, $category);
    }

    private function readSource(): ProductReadSource
    {
        return $this->readSource ?? $this->products;
    }

    private function filterString(array $query, string $field, array &$errors): ?string
    {
        if (!array_key_exists($field, $query)) {
            return null;
        }
        if (!is_string($query[$field])) {
            $errors[$field] = 'Filter must be a string';

            return null;
        }

        $value = trim($query[$field]);
        if ($value === '') {
            $errors[$field] = 'Filter must not be empty';
        }
        if (strlen($value) > 255) {
            $errors[$field] = 'Filter must not exceed 255 characters';
        }

        return $value;
    }

    private function filterCategory(array $query, array &$errors): ?int
    {
        if (!array_key_exists('category', $query)) {
            return null;
        }
        $value = $query['category'];
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            $errors['category'] = 'Category must be a positive integer';

            return null;
        }

        $category = (int) $value;
        if ($category < 1) {
            $errors['category'] = 'Category must be a positive integer';
        }

        return $category;
    }
}
