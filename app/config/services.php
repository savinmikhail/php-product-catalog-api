<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Category\CategoryInputValidator;
use App\Modules\Catalog\Application\Category\CreateCategory;
use App\Modules\Catalog\Application\Category\DeleteCategory;
use App\Modules\Catalog\Application\Category\GetCategory;
use App\Modules\Catalog\Application\Category\ListCategories;
use App\Modules\Catalog\Application\Category\UpdateCategory;
use App\Modules\Catalog\Application\Product\CreateProduct;
use App\Modules\Catalog\Application\Product\DeleteProduct;
use App\Modules\Catalog\Application\Product\GetProduct;
use App\Modules\Catalog\Application\Product\ProductInputValidator;
use App\Modules\Catalog\Application\Product\SearchProducts;
use App\Modules\Catalog\Application\Product\UpdateProduct;
use App\Modules\Catalog\Infrastructure\Persistence\PdoCategoryRepository;
use App\Modules\Catalog\Infrastructure\Persistence\PdoProductRepository;
use App\Modules\Catalog\Infrastructure\Search\ElasticsearchClient;
use App\Modules\Catalog\Infrastructure\Search\ElasticsearchProductIndexer;
use App\Modules\Catalog\Infrastructure\Search\ElasticsearchProductReadSource;
use App\Modules\Catalog\Infrastructure\Search\FallbackProductReadSource;
use App\Modules\Catalog\Infrastructure\Search\HttpElasticsearchTransport;
use App\Modules\Catalog\Infrastructure\Search\ElasticsearchTransport;
use App\Modules\Catalog\Infrastructure\Search\ProductReindexer;
use App\Modules\Catalog\Port\CategoryRepository;
use App\Modules\Catalog\Port\ProductIndexer;
use App\Modules\Catalog\Port\ProductReadSource;
use App\Modules\Catalog\Port\ProductRepository;
use App\Modules\Catalog\Presentation\Http\CategoryController;
use App\Modules\Catalog\Presentation\Http\ProductController;
use App\Modules\Health\Presentation\Http\HealthController;
use App\Modules\InnValidation\Infrastructure\DaData\DadataHttpClient;
use App\Modules\InnValidation\Infrastructure\DaData\DadataInnValidator;
use App\Modules\InnValidation\Infrastructure\DaData\TtlValidationCache;
use App\Modules\InnValidation\Port\InnValidator;
use App\Modules\InnValidation\Port\ValidationCache;
use App\Shared\Clock\Clock;
use App\Shared\Clock\SystemClock;
use App\Shared\Infrastructure\Configuration\Config;
use App\Shared\Infrastructure\Configuration\PdoConnectionFactory;
use App\Shared\Infrastructure\Http\HttpTransport;
use App\Shared\Infrastructure\Http\StreamHttpTransport;
use App\Shared\Presentation\Http\JsonExceptionHandler;
use App\Shared\Presentation\Http\Kernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

$container = new ContainerBuilder();
$config = Config::fromEnvironment();

$container->set(Config::class, $config);
$container->register(PDO::class)
    ->setClass(PDO::class)
    ->setPublic(true)
    ->setFactory([PdoConnectionFactory::class, 'create'])
    ->addArgument(new Reference(Config::class));

$register = static function (string $class) use ($container): void {
    $container->register($class)
        ->setAutowired(true)
        ->setAutoconfigured(true)
        ->setPublic(true);
};

foreach ([
    StreamHttpTransport::class,
    SystemClock::class,
    TtlValidationCache::class,
    DadataInnValidator::class,
    ElasticsearchProductIndexer::class,
    ElasticsearchProductReadSource::class,
    ProductReindexer::class,
    ProductInputValidator::class,
    CategoryInputValidator::class,
    CreateProduct::class,
    UpdateProduct::class,
    DeleteProduct::class,
    GetProduct::class,
    SearchProducts::class,
    CreateCategory::class,
    UpdateCategory::class,
    DeleteCategory::class,
    GetCategory::class,
    ListCategories::class,
    ProductController::class,
    CategoryController::class,
    HealthController::class,
    JsonExceptionHandler::class,
    Kernel::class,
] as $class) {
    $register($class);
}

$container->setAlias(HttpTransport::class, StreamHttpTransport::class);
$container->setAlias(Clock::class, SystemClock::class);
$container->setAlias(ValidationCache::class, TtlValidationCache::class);
$container->setAlias(InnValidator::class, DadataInnValidator::class);

$container->register(DadataHttpClient::class)
    ->setAutowired(true)
    ->setPublic(true)
    ->setArgument('$endpoint', $config->string(
        'DADATA_API_URL',
        'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/party',
    ))
    ->setArgument('$token', $config->string('DADATA_API_TOKEN'))
    ->setArgument('$timeoutSeconds', $config->int('DADATA_HTTP_TIMEOUT', 3));

$container->register(HttpElasticsearchTransport::class)
    ->setAutowired(true)
    ->setPublic(true)
    ->setArgument('$baseUrl', $config->string('ELASTICSEARCH_URL', 'http://elasticsearch:9200'))
    ->setArgument('$timeoutSeconds', $config->int('ELASTICSEARCH_TIMEOUT', 5));
$container->setAlias(ElasticsearchTransport::class, HttpElasticsearchTransport::class);
$container->register(ElasticsearchClient::class)
    ->setAutowired(true)
    ->setPublic(true)
    ->setArgument('$index', $config->string('ELASTICSEARCH_INDEX', 'products'));

$container->register(PdoProductRepository::class)->setAutowired(true)->setPublic(true);
$container->register(PdoCategoryRepository::class)->setAutowired(true)->setPublic(true);
$container->setAlias(ProductRepository::class, PdoProductRepository::class);
$container->setAlias(CategoryRepository::class, PdoCategoryRepository::class);
$container->setAlias(ProductIndexer::class, ElasticsearchProductIndexer::class);

$container->register(FallbackProductReadSource::class)
    ->setAutowired(true)
    ->setPublic(true)
    ->setArgument('$primary', new Reference(ElasticsearchProductReadSource::class))
    ->setArgument('$fallback', new Reference(ProductRepository::class));
$container->setAlias(ProductReadSource::class, FallbackProductReadSource::class);

$container->compile();

return $container;
