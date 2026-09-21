<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Http\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

$container = Bootstrap::container();

$container->get(App\Http\Kernel::class)->handle(Request::fromGlobals())->send();
