<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation\Http;

use App\Modules\Catalog\Application\Category\CreateCategory;
use App\Modules\Catalog\Application\Category\DeleteCategory;
use App\Modules\Catalog\Application\Category\GetCategory;
use App\Modules\Catalog\Application\Category\ListCategories;
use App\Modules\Catalog\Application\Category\UpdateCategory;
use App\Shared\Presentation\Http\Request;
use App\Shared\Presentation\Http\Response;

final class CategoryController
{
    public function __construct(
        private readonly CreateCategory $createCategory,
        private readonly UpdateCategory $updateCategory,
        private readonly DeleteCategory $deleteCategory,
        private readonly GetCategory $getCategory,
        private readonly ListCategories $listCategories,
    )
    {
    }

    public function index(): Response
    {
        return new Response(['data' => array_map(static fn ($category): array => $category->toArray(), ($this->listCategories)())]);
    }

    public function create(Request $request): Response
    {
        return new Response(['data' => ($this->createCategory)($request->body)->toArray()], 201);
    }

    public function show(int $id): Response
    {
        return new Response(['data' => ($this->getCategory)($id)->toArray()]);
    }

    public function update(int $id, Request $request): Response
    {
        return new Response(['data' => ($this->updateCategory)($id, $request->body)->toArray()]);
    }

    public function delete(int $id): Response
    {
        ($this->deleteCategory)($id);

        return new Response(null, 204);
    }
}
