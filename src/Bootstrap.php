<?php

declare(strict_types=1);

namespace App;

use App\Catalog\Application\CatalogService;
use App\Catalog\Http\CategoryController;
use App\Catalog\Http\ProductController;
use App\Catalog\Indexing\ElasticsearchProductIndexer;
use App\Catalog\Indexing\ProductIndexer;
use App\Catalog\Indexing\ProductReindexer;
use App\Catalog\Persistence\PdoCategoryRepository;
use App\Catalog\Persistence\PdoProductRepository;
use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Repository\ProductRepository;
use App\Config\Config;
use App\Elasticsearch\ElasticsearchClient;
use App\Elasticsearch\ElasticsearchTransport;
use App\Elasticsearch\HttpElasticsearchTransport;
use App\Health\HealthController;
use App\Http\JsonExceptionHandler;
use App\Http\Kernel;
use PDO;

final class Bootstrap
{
    public static function container(): Container
    {
        $container = new Container();
        $container->set(Config::class, Config::fromEnvironment());
        $container->set(JsonExceptionHandler::class, new JsonExceptionHandler());
        $container->set(HealthController::class, new HealthController());
        $container->set(PDO::class, static function (Container $container): PDO {
            $config = $container->get(Config::class);
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $config->string('DB_HOST', 'mysql'),
                $config->int('DB_PORT', 3306),
                $config->string('DB_DATABASE', 'catalog'),
            );
            $pdo = new PDO($dsn, $config->string('DB_USERNAME', 'catalog'), $config->string('DB_PASSWORD', 'catalog'));
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            return $pdo;
        });
        $container->set(ProductRepository::class, static fn (Container $container): ProductRepository => new PdoProductRepository($container->get(PDO::class)));
        $container->set(CategoryRepository::class, static fn (Container $container): CategoryRepository => new PdoCategoryRepository($container->get(PDO::class)));
        $container->set(ElasticsearchTransport::class, static fn (Container $container): ElasticsearchTransport => new HttpElasticsearchTransport(
            $container->get(Config::class)->string('ELASTICSEARCH_URL', 'http://elasticsearch:9200'),
            $container->get(Config::class)->int('ELASTICSEARCH_TIMEOUT', 5),
        ));
        $container->set(ElasticsearchClient::class, static fn (Container $container): ElasticsearchClient => new ElasticsearchClient(
            $container->get(ElasticsearchTransport::class),
            $container->get(Config::class)->string('ELASTICSEARCH_INDEX', 'products'),
        ));
        $container->set(ProductIndexer::class, static fn (Container $container): ProductIndexer => new ElasticsearchProductIndexer(
            $container->get(ElasticsearchClient::class),
        ));
        $container->set(ProductReindexer::class, static fn (Container $container): ProductReindexer => new ProductReindexer(
            $container->get(ProductRepository::class),
            $container->get(ProductIndexer::class),
        ));
        $container->set(CatalogService::class, static fn (Container $container): CatalogService => new CatalogService(
            $container->get(ProductRepository::class),
            $container->get(CategoryRepository::class),
            $container->get(ProductIndexer::class),
        ));
        $container->set(ProductController::class, static fn (Container $container): ProductController => new ProductController($container->get(CatalogService::class)));
        $container->set(CategoryController::class, static fn (Container $container): CategoryController => new CategoryController($container->get(CatalogService::class)));
        $container->set(Kernel::class, static fn (Container $container): Kernel => new Kernel(
            $container->get(HealthController::class),
            $container->get(ProductController::class),
            $container->get(CategoryController::class),
            $container->get(JsonExceptionHandler::class),
        ));

        return $container;
    }
}
