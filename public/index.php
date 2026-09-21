<?php

declare(strict_types=1);

use App\Container;
use App\Health\HealthController;
use App\Http\JsonExceptionHandler;
use App\Http\Kernel;
use App\Http\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = new Container();
$container->set(JsonExceptionHandler::class, new JsonExceptionHandler());
$container->set(HealthController::class, new HealthController());
$container->set(Kernel::class, static fn (Container $container): Kernel => new Kernel(
    $container->get(HealthController::class),
    $container->get(JsonExceptionHandler::class),
));

$container->get(Kernel::class)->handle(Request::fromGlobals())->send();
