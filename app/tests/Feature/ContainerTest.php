<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Application\Product\CreateProduct;
use App\Shared\Presentation\Http\Kernel;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class ContainerTest extends TestCase
{
    public function testCompositionRootBuildsPsr11ContainerWithAutowiredUseCases(): void
    {
        $container = require dirname(__DIR__, 2) . '/config/services.php';

        self::assertInstanceOf(ContainerInterface::class, $container);
        self::assertTrue($container->has(CreateProduct::class));
        self::assertTrue($container->has(Kernel::class));
    }
}
