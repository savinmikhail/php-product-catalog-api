<?php

declare(strict_types=1);

namespace App\Shared\Error;

final class IndexingException extends ApiException
{
    public function __construct(string $message = 'Catalog index synchronization failed')
    {
        parent::__construct('indexing_failed', 503, $message);
    }
}
