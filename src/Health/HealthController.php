<?php

declare(strict_types=1);

namespace App\Health;

use App\Http\Response;

final class HealthController
{
    public function __invoke(): Response
    {
        return new Response(['data' => ['status' => 'ok']]);
    }
}
