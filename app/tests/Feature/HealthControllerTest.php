<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Health\Presentation\Http\HealthController;
use PHPUnit\Framework\TestCase;

final class HealthControllerTest extends TestCase
{
    public function testHealthEndpointReturnsJsonReadyPayload(): void
    {
        $response = (new HealthController())();

        self::assertSame(200, $response->status);
        self::assertSame(['data' => ['status' => 'ok']], $response->payload);
    }
}
