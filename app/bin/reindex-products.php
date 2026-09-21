#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Search\ProductReindexer;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $container = require dirname(__DIR__) . '/config/services.php';
    $count = $container->get(ProductReindexer::class)->reindex();
    fwrite(STDOUT, sprintf("Reindexed %d products.\n", $count));
} catch (Throwable $exception) {
    fwrite(STDERR, 'Product reindex failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
