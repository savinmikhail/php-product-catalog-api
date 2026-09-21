<?php

declare(strict_types=1);

namespace App\Catalog\Http;

use App\Catalog\Application\CatalogService;
use App\Http\Request;
use App\Http\Response;

final class CategoryController
{
    public function __construct(private readonly CatalogService $catalog)
    {
    }

    public function index(): Response
    {
        return new Response(['data' => array_map(static fn ($category): array => $category->toArray(), $this->catalog->categories())]);
    }

    public function create(Request $request): Response
    {
        return new Response(['data' => $this->catalog->createCategory($request->body)->toArray()], 201);
    }

    public function show(int $id): Response
    {
        return new Response(['data' => $this->catalog->category($id)->toArray()]);
    }

    public function update(int $id, Request $request): Response
    {
        return new Response(['data' => $this->catalog->updateCategory($id, $request->body)->toArray()]);
    }

    public function delete(int $id): Response
    {
        $this->catalog->deleteCategory($id);

        return new Response(null, 204);
    }
}
