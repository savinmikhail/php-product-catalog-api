<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Category;

use App\Modules\Catalog\Domain\Category;
use App\Modules\Catalog\Port\CategoryRepository;
use App\Shared\Error\NotFoundException;

final class GetCategory
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    public function __invoke(int $id): Category
    {
        return $this->categories->find($id) ?? throw new NotFoundException('Category not found');
    }
}
