<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Category;

use App\Modules\Catalog\Domain\Category;
use App\Modules\Catalog\Port\CategoryRepository;

final class ListCategories
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    /** @return list<Category> */
    public function __invoke(): array
    {
        return $this->categories->all();
    }
}
