<?php

declare(strict_types=1);

use App\Shared\Presentation\Http\Kernel;
use App\Shared\Presentation\Http\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = require dirname(__DIR__) . '/config/services.php';

$container->get(Kernel::class)->handle(Request::fromGlobals())->send();
