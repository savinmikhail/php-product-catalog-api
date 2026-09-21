<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation\Http;

use App\Modules\Catalog\Application\Product\CreateProduct;
use App\Modules\Catalog\Application\Product\DeleteProduct;
use App\Modules\Catalog\Application\Product\GetProduct;
use App\Modules\Catalog\Application\Product\SearchProducts;
use App\Modules\Catalog\Application\Product\UpdateProduct;
use App\Shared\Presentation\Http\Request;
use App\Shared\Presentation\Http\Response;

final class ProductController
{
    public function __construct(
        private readonly CreateProduct $createProduct,
        private readonly UpdateProduct $updateProduct,
        private readonly DeleteProduct $deleteProduct,
        private readonly GetProduct $getProduct,
        private readonly SearchProducts $searchProducts,
    )
    {
    }

    public function index(Request $request): Response
    {
        return new Response(['data' => array_map(
            static fn ($product): array => $product->toArray(),
            ($this->searchProducts)($request->query),
        )]);
    }

    public function create(Request $request): Response
    {
        return new Response(['data' => ($this->createProduct)($request->body)->toArray()], 201);
    }

    public function show(int $id): Response
    {
        return new Response(['data' => ($this->getProduct)($id)->toArray()]);
    }

    public function update(int $id, Request $request): Response
    {
        return new Response(['data' => ($this->updateProduct)($id, $request->body)->toArray()]);
    }

    public function delete(int $id): Response
    {
        ($this->deleteProduct)($id);

        return new Response(null, 204);
    }
}
