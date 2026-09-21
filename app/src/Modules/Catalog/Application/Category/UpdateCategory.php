<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Category;

use App\Modules\Catalog\Domain\Category;
use App\Modules\Catalog\Port\CategoryRepository;
use App\Shared\Error\ConflictException;
use App\Shared\Error\NotFoundException;

final class UpdateCategory
{
    public function __construct(
        private readonly CategoryRepository $categories,
        private readonly CategoryInputValidator $input,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function __invoke(int $id, array $data): Category
    {
        $this->categories->find($id) ?? throw new NotFoundException('Category not found');
        $name = $this->input->name($data);
        if ($this->categories->existsByName($name, $id)) {
            throw new ConflictException('Category name must be unique');
        }

        return $this->categories->update($id, $name);
    }
}
