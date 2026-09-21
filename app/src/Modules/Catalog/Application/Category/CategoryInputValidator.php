<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Category;

use App\Shared\Error\ValidationException;

final class CategoryInputValidator
{
    /** @param array<string, mixed> $input */
    public function name(array $input): string
    {
        $errors = [];
        if (!array_key_exists('name', $input)) {
            $errors['name'] = 'Field is required';
        } elseif (!is_string($input['name'])) {
            $errors['name'] = 'Field must be a string';
        } else {
            $name = trim($input['name']);
            if ($name === '') {
                $errors['name'] = 'Field must not be empty';
            }
            if (strlen($name) > 255) {
                $errors['name'] = 'Field must not exceed 255 characters';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return trim($input['name']);
    }
}
