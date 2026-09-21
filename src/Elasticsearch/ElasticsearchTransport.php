<?php

declare(strict_types=1);

namespace App\Elasticsearch;

interface ElasticsearchTransport
{
    public function request(string $method, string $path, ?array $body = null): ElasticsearchResponse;
}
