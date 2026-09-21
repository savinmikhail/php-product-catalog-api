<?php

declare(strict_types=1);

namespace App\Catalog\Http;

use App\Catalog\Application\CatalogService;
use App\Http\Request;
use App\Http\Response;

final class ProductController
{
    public function __construct(private readonly CatalogService $catalog)
    {
    }

    public function index(Request $request): Response
    {
        return new Response(['data' => array_map(
            static fn ($product): array => $product->toArray(),
            $this->catalog->products($request->query),
        )]);
    }

    public function create(Request $request): Response
    {
        return new Response(['data' => $this->catalog->createProduct($request->body)->toArray()], 201);
    }

    public function show(int $id): Response
    {
        return new Response(['data' => $this->catalog->product($id)->toArray()]);
    }

    public function update(int $id, Request $request): Response
    {
        return new Response(['data' => $this->catalog->updateProduct($id, $request->body)->toArray()]);
    }

    public function delete(int $id): Response
    {
        $this->catalog->deleteProduct($id);

        return new Response(null, 204);
    }
}
