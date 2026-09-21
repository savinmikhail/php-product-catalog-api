<?php

declare(strict_types=1);

namespace App\Http;

use App\Catalog\Http\CategoryController;
use App\Catalog\Http\ProductController;
use App\Health\HealthController;
use App\Shared\Exception\ApiException;
use App\Shared\Exception\NotFoundException;
use Throwable;

final class Kernel
{
    public function __construct(
        private readonly HealthController $health,
        private readonly ProductController $products,
        private readonly CategoryController $categories,
        private readonly JsonExceptionHandler $exceptions,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            if ($request->method === 'GET' && $request->path === '/health') {
                return ($this->health)();
            }

            if ($request->path === '/products') {
                return match ($request->method) {
                    'GET' => $this->products->index($request),
                    'POST' => $this->products->create($request),
                    default => throw new NotFoundException('Route not found'),
                };
            }
            if (preg_match('#^/products/([1-9][0-9]*)$#', $request->path, $matches) === 1) {
                $id = (int) $matches[1];

                return match ($request->method) {
                    'GET' => $this->products->show($id),
                    'PUT', 'PATCH' => $this->products->update($id, $request),
                    'DELETE' => $this->products->delete($id),
                    default => throw new NotFoundException('Route not found'),
                };
            }
            if ($request->path === '/categories') {
                return match ($request->method) {
                    'GET' => $this->categories->index(),
                    'POST' => $this->categories->create($request),
                    default => throw new NotFoundException('Route not found'),
                };
            }
            if (preg_match('#^/categories/([1-9][0-9]*)$#', $request->path, $matches) === 1) {
                $id = (int) $matches[1];

                return match ($request->method) {
                    'GET' => $this->categories->show($id),
                    'PUT', 'PATCH' => $this->categories->update($id, $request),
                    'DELETE' => $this->categories->delete($id),
                    default => throw new NotFoundException('Route not found'),
                };
            }

            throw new NotFoundException('Route not found');
        } catch (ApiException|Throwable $exception) {
            return $this->exceptions->response($exception);
        }
    }
}
