<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Product;

use App\Modules\Catalog\Domain\Category;
use App\Modules\Catalog\Domain\Product;
use App\Modules\Catalog\Domain\ProductFilters;
use App\Shared\Error\ValidationException;

final class ProductInputValidator
{
    public function forCreate(array $input): ProductInput
    {
        return $this->product($input);
    }

    public function forUpdate(array $input, Product $current): ProductInput
    {
        return $this->product($input, $current);
    }

    public function filters(array $query): ProductFilters
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

    /** @return array{name:string,inn:string,ean13:string,description:string,category_ids:list<int>} */
    private function normalizedProduct(array $input, ?Product $current = null): array
    {
        $errors = [];
        $name = $this->stringValue($input, 'name', $current?->name, $errors, true);
        $inn = $this->stringValue($input, 'inn', $current?->inn, $errors, true);
        $ean13 = $this->stringValue($input, 'ean13', $current?->ean13, $errors, true);
        $description = $this->stringValue(
            $input,
            'description',
            $current?->description,
            $errors,
            true,
            allowEmpty: true,
        );

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

    private function product(array $input, ?Product $current = null): ProductInput
    {
        $data = $this->normalizedProduct($input, $current);

        return new ProductInput(
            $data['name'],
            $data['inn'],
            $data['ean13'],
            $data['description'],
            $data['category_ids'],
        );
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
    private function categoryIds(?Product $product): array
    {
        return $product === null ? [] : array_map(
            static fn (Category $category): int => $category->id,
            $product->categories,
        );
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
