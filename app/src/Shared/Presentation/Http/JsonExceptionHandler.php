<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http;

use App\Shared\Error\ApiException;
use Throwable;

final class JsonExceptionHandler
{
    public function response(Throwable $exception): Response
    {
        if ($exception instanceof ApiException) {
            $error = ['code' => $exception->codeName(), 'message' => $exception->getMessage()];
            if ($exception->details() !== []) {
                $error['details'] = $exception->details();
            }

            return new Response(['error' => $error], $exception->status());
        }

        return new Response(['error' => ['code' => 'internal_error', 'message' => 'Internal server error']], 500);
    }
}
