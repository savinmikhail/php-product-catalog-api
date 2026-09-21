<?php

declare(strict_types=1);

namespace Tests;

use App\Container;
use App\Health\HealthController;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    public function testInvokableObjectIsRegisteredAsAnInstance(): void
    {
        $container = new Container();
        $health = new HealthController();
        $container->set(HealthController::class, $health);

        self::assertSame($health, $container->get(HealthController::class));
    }
}
