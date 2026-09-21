<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Product;

use App\Modules\Catalog\Port\ProductReadSource;

final class SearchProducts
{
    public function __construct(
        private readonly ProductReadSource $readSource,
        private readonly ProductInputValidator $input,
    ) {
    }

    /** @param array<string, mixed> $query @return list<\App\Modules\Catalog\Domain\Product> */
    public function __invoke(array $query = []): array
    {
        return $this->readSource->search($this->input->filters($query));
    }
}
