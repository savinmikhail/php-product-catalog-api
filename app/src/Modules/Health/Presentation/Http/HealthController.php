<?php

declare(strict_types=1);

namespace App\Modules\Health\Presentation\Http;

use App\Shared\Presentation\Http\Response;

final class HealthController
{
    public function __invoke(): Response
    {
        return new Response(['data' => ['status' => 'ok']]);
    }
}
