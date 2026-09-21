<?php

declare(strict_types=1);

namespace Tests\Catalog;

use App\Catalog\Application\CatalogService;
use App\Catalog\Http\CategoryController;
use App\Catalog\Http\ProductController;
use App\Catalog\Persistence\PdoCategoryRepository;
use App\Catalog\Persistence\PdoProductRepository;
use App\Http\JsonExceptionHandler;
use App\Http\Kernel;
use App\Http\Request;
use PDO;
use PHPUnit\Framework\TestCase;

final class CatalogHttpTest extends TestCase
{
    private Kernel $kernel;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec(<<<'SQL'
            PRAGMA foreign_keys = ON;
            CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL UNIQUE,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            );
            CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                inn VARCHAR(12) NOT NULL,
                ean13 CHAR(13) NOT NULL,
                description TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE (inn, ean13)
            );
            CREATE TABLE product_categories (
                product_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                PRIMARY KEY (product_id, category_id),
                FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
            );
            SQL);

        $catalog = new CatalogService(new PdoProductRepository($pdo), new PdoCategoryRepository($pdo));
        $this->kernel = new Kernel(
            new \App\Health\HealthController(),
            new ProductController($catalog),
            new CategoryController($catalog),
            new JsonExceptionHandler(),
        );
    }

    public function testProductCanBeCreatedReadUpdatedAndDeletedWithSeveralCategories(): void
    {
        $books = $this->request('POST', '/categories', ['name' => 'Books']);
        $gifts = $this->request('POST', '/categories', ['name' => 'Gifts']);
        self::assertSame(201, $books->status);
        self::assertSame(201, $gifts->status);

        $created = $this->request('POST', '/products', [
            'name' => 'API Handbook',
            'inn' => '7701234567',
            'ean13' => '4601234567890',
            'description' => 'A practical guide',
            'category_ids' => [$books->payload['data']['id'], $gifts->payload['data']['id']],
        ]);

        self::assertSame(201, $created->status);
        self::assertSame('API Handbook', $created->payload['data']['name']);
        self::assertCount(2, $created->payload['data']['categories']);

        $id = $created->payload['data']['id'];
        $read = $this->request('GET', '/products/' . $id);
        self::assertSame(200, $read->status);
        self::assertSame($created->payload['data'], $read->payload['data']);

        $updated = $this->request('PATCH', '/products/' . $id, [
            'name' => 'API Handbook, 2nd edition',
            'description' => 'Updated guide',
            'category_ids' => [$gifts->payload['data']['id']],
        ]);
        self::assertSame(200, $updated->status);
        self::assertSame('API Handbook, 2nd edition', $updated->payload['data']['name']);
        self::assertCount(1, $updated->payload['data']['categories']);

        $deleted = $this->request('DELETE', '/products/' . $id);
        self::assertSame(204, $deleted->status);
        self::assertSame(404, $this->request('GET', '/products/' . $id)->status);
    }

    public function testProductIdentityIsUniqueAndInvalidInputIsReturnedAsJson(): void
    {
        $body = [
            'name' => 'First',
            'inn' => '7701234567',
            'ean13' => '4601234567890',
            'description' => 'Description',
        ];
        self::assertSame(201, $this->request('POST', '/products', $body)->status);

        $duplicate = $this->request('POST', '/products', $body);
        self::assertSame(409, $duplicate->status);
        self::assertSame('conflict', $duplicate->payload['error']['code']);

        $invalid = $this->request('POST', '/products', [
            'name' => '',
            'inn' => '123',
            'ean13' => 'not-an-ean',
        ]);
        self::assertSame(422, $invalid->status);
        self::assertSame('validation_error', $invalid->payload['error']['code']);
        self::assertArrayHasKey('description', $invalid->payload['error']['details']);
    }

    public function testCategoryCanBeUpdatedAndMissingCategoryCannotBeAssigned(): void
    {
        $created = $this->request('POST', '/categories', ['name' => 'Initial']);
        $id = $created->payload['data']['id'];

        $updated = $this->request('PUT', '/categories/' . $id, ['name' => 'Renamed']);
        self::assertSame(200, $updated->status);
        self::assertSame('Renamed', $updated->payload['data']['name']);

        $invalid = $this->request('POST', '/products', [
            'name' => 'Product',
            'inn' => '7701234568',
            'ean13' => '4601234567891',
            'description' => 'Description',
            'category_ids' => [999],
        ]);
        self::assertSame(422, $invalid->status);
        self::assertStringContainsString('999', $invalid->payload['error']['details']['category_ids']);
    }

    public function testProductsCanBeFilteredByNameEanInnAndCategory(): void
    {
        $food = $this->request('POST', '/categories', ['name' => 'Food']);
        $books = $this->request('POST', '/categories', ['name' => 'Books']);
        $foodId = $food->payload['data']['id'];
        $booksId = $books->payload['data']['id'];

        $this->createProduct('Red Apple', '7701234567', '4601234567890', [$foodId]);
        $this->createProduct('Green Apple', '7701234568', '4601234567891', [$foodId]);
        $this->createProduct('Red Book', '7701234569', '4601234567892', [$booksId]);

        self::assertSame(
            ['Red Apple', 'Green Apple'],
            $this->productNames($this->request('GET', '/products', ['name' => 'apple'])),
        );
        self::assertSame(
            ['Green Apple'],
            $this->productNames($this->request('GET', '/products', ['inn' => '7701234568'])),
        );
        self::assertSame(
            ['Red Book'],
            $this->productNames($this->request('GET', '/products', ['ean13' => '4601234567892'])),
        );
        self::assertSame(
            ['Red Apple', 'Green Apple'],
            $this->productNames($this->request('GET', '/products', ['category' => (string) $foodId])),
        );
        self::assertSame(
            ['Red Apple'],
            $this->productNames($this->request('GET', '/products', [
                'name' => 'red',
                'inn' => '7701234567',
                'ean13' => '4601234567890',
                'category' => (string) $foodId,
            ])),
        );
    }

    public function testProductSearchReturnsEmptyResultsAndJsonForInvalidParameters(): void
    {
        $this->createProduct('API Handbook', '7701234567', '4601234567890');

        $empty = $this->request('GET', '/products', ['name' => 'missing']);
        self::assertSame(200, $empty->status);
        self::assertSame([], $empty->payload['data']);

        $invalid = $this->request('GET', '/products', [
            'inn' => '123',
            'ean13' => 'not-an-ean',
            'category' => '0',
        ]);
        self::assertSame(422, $invalid->status);
        self::assertSame('validation_error', $invalid->payload['error']['code']);
        self::assertArrayHasKey('inn', $invalid->payload['error']['details']);
        self::assertArrayHasKey('ean13', $invalid->payload['error']['details']);
        self::assertArrayHasKey('category', $invalid->payload['error']['details']);
    }

    private function createProduct(string $name, string $inn, string $ean13, array $categoryIds = []): void
    {
        $response = $this->request('POST', '/products', [
            'name' => $name,
            'inn' => $inn,
            'ean13' => $ean13,
            'description' => 'Description',
            'category_ids' => $categoryIds,
        ]);

        self::assertSame(201, $response->status);
    }

    /** @return list<string> */
    private function productNames(\App\Http\Response $response): array
    {
        self::assertSame(200, $response->status);

        return array_column($response->payload['data'], 'name');
    }

    private function request(string $method, string $path, array $data = [], array $query = []): \App\Http\Response
    {
        return $this->kernel->handle(new Request(
            $method,
            $path,
            $method === 'GET' ? $data : $query,
            $method === 'GET' ? [] : $data,
        ));
    }
}
