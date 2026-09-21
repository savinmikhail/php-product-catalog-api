<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Category;

use App\Modules\Catalog\Domain\Category;
use App\Modules\Catalog\Port\CategoryRepository;
use App\Shared\Error\ConflictException;

final class CreateCategory
{
    public function __construct(
        private readonly CategoryRepository $categories,
        private readonly CategoryInputValidator $input,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function __invoke(array $data): Category
    {
        $name = $this->input->name($data);
        if ($this->categories->existsByName($name)) {
            throw new ConflictException('Category name must be unique');
        }

        return $this->categories->create($name);
    }
}
