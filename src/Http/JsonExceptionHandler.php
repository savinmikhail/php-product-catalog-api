<?php

declare(strict_types=1);

namespace App\Http;

use App\Shared\Exception\ApiException;
use Throwable;

final class JsonExceptionHandler
{
    public function response(Throwable $exception): Response
    {
        if ($exception instanceof ApiException) {
            return new Response(['error' => ['code' => $exception->codeName(), 'message' => $exception->getMessage()]], $exception->status());
        }

        return new Response(['error' => ['code' => 'internal_error', 'message' => 'Internal server error']], 500);
    }
}
