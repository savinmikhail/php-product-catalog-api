#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Catalog\Indexing\ProductReindexer;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $count = Bootstrap::container()->get(ProductReindexer::class)->reindex();
    fwrite(STDOUT, sprintf("Reindexed %d products.\n", $count));
} catch (Throwable $exception) {
    fwrite(STDERR, 'Product reindex failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
