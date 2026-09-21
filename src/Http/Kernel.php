<?php

declare(strict_types=1);

namespace App\Http;

use App\Health\HealthController;
use App\Shared\Exception\ApiException;
use App\Shared\Exception\NotFoundException;
use Throwable;

final class Kernel
{
    public function __construct(
        private readonly HealthController $health,
        private readonly JsonExceptionHandler $exceptions,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            if ($request->method === 'GET' && $request->path === '/health') {
                return ($this->health)();
            }

            throw new NotFoundException('Route not found');
        } catch (ApiException|Throwable $exception) {
            return $this->exceptions->response($exception);
        }
    }
}
