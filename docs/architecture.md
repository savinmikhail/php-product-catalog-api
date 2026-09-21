# Architecture

## Layout

```text
app/
├── config/services.php                 composition root
├── public/index.php                    HTTP entrypoint
├── bin/reindex-products.php            CLI entrypoint
├── src/
│   ├── Shared/
│   │   ├── Presentation/Http/          request, response, routing, errors
│   │   ├── Infrastructure/Http/        generic outbound HTTP transport
│   │   ├── Infrastructure/Configuration/
│   │   ├── Error/                      API error types
│   │   └── Clock/                      generic time seam
│   └── Modules/
│       ├── Catalog/
│       │   ├── Domain/
│       │   ├── Application/Product/    product use cases
│       │   ├── Application/Category/   category use cases
│       │   ├── Port/                   repository, read and indexer seams
│       │   ├── Infrastructure/Persistence/
│       │   ├── Infrastructure/Search/  Elasticsearch and fallback adapters
│       │   └── Presentation/Http/      product/category controllers
│       ├── InnValidation/
│       │   ├── Port/                   InnValidator and cache seams
│       │   └── Infrastructure/DaData/ DaData adapters and TTL cache
│       └── Health/Presentation/Http/
└── tests/
    ├── Unit/
    └── Feature/
```

## Dependency flow

`public/index.php` and the reindex command load the Symfony DependencyInjection
container from `config/services.php`. The container is PSR-11 compatible and
is never passed to a domain object or use case. Constructor injection and
autowiring build the object graph; interface aliases bind ports to adapters.

The HTTP kernel routes requests to Catalog controllers. Controllers only map
HTTP input/output and invoke product or category use cases. Use cases own
validation, not-found/conflict checks, repository writes, INN validation and
index synchronization.

Catalog depends on these narrow ports:

- `ProductRepository` and `CategoryRepository` for MySQL persistence;
- `ProductReadSource` for reads, configured as Elasticsearch with MySQL
  fallback;
- `ProductIndexer` for create/update/delete synchronization and full reindex.

INN validation is a separate module. Catalog sees only the
`InnValidation\Port\InnValidator` contract. DaData HTTP mapping, the cache and
transport-specific failures remain in the InnValidation infrastructure.

Elasticsearch is a Catalog adapter, including its HTTP client, index lifecycle,
document mapping, search source, fallback and reindexer. Only the generic
outbound HTTP transport is shared.

## Preserved behavior

The refactor keeps the existing JSON shapes, status codes, CRUD routes,
validation messages, DaData error mapping, indexing errors, Elasticsearch
search filters, MySQL fallback and reindex command. A successful MySQL write is
still retained when a subsequent index synchronization fails; that failure is
reported as `indexing_failed`.
