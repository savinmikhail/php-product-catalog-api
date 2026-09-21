<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Domain\Category;
use App\Modules\Catalog\Domain\Product;
use App\Modules\Catalog\Domain\ProductFilters;
use App\Modules\Catalog\Port\ProductRepository;
use App\Shared\Error\ConflictException;
use PDO;
use PDOException;

final class PdoProductRepository implements ProductRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        return $this->search(new ProductFilters());
    }

    public function search(ProductFilters $filters): array
    {
        $conditions = [];
        $parameters = [];

        if ($filters->name !== null) {
            $conditions[] = "LOWER(p.name) LIKE LOWER(:name) ESCAPE '!'";
            $parameters['name'] = '%' . strtr($filters->name, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        }
        if ($filters->inn !== null) {
            $conditions[] = 'p.inn = :inn';
            $parameters['inn'] = $filters->inn;
        }
        if ($filters->ean13 !== null) {
            $conditions[] = 'p.ean13 = :ean13';
            $parameters['ean13'] = $filters->ean13;
        }
        if ($filters->categoryId !== null) {
            $conditions[] = 'EXISTS ('
                . 'SELECT 1 FROM product_categories pc '
                . 'WHERE pc.product_id = p.id AND pc.category_id = :category_id)';
            $parameters['category_id'] = $filters->categoryId;
        }

        $sql = 'SELECT p.id, p.name, p.inn, p.ean13, p.description FROM products p';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY p.id';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return array_map(fn (array $row): Product => $this->hydrate($row), $statement->fetchAll());
    }

    public function find(int $id): ?Product
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, inn, ean13, description FROM products WHERE id = :id',
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function create(string $name, string $inn, string $ean13, string $description, array $categoryIds): Product
    {
        return $this->transactional(function () use ($name, $inn, $ean13, $description, $categoryIds): Product {
            try {
                $statement = $this->pdo->prepare(
                    'INSERT INTO products (name, inn, ean13, description, created_at, updated_at) '
                    . 'VALUES (:name, :inn, :ean13, :description, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
                );
                $statement->execute([
                    'name' => $name,
                    'inn' => $inn,
                    'ean13' => $ean13,
                    'description' => $description,
                ]);
            } catch (PDOException $exception) {
                $this->throwIfUniqueViolation($exception);
                throw $exception;
            }

            $id = (int) $this->pdo->lastInsertId();
            $this->replaceCategories($id, $categoryIds);

            return $this->find($id) ?? throw new PDOException('Created product cannot be loaded');
        });
    }

    public function update(int $id, string $name, string $inn, string $ean13, string $description, array $categoryIds): Product
    {
        return $this->transactional(function () use ($id, $name, $inn, $ean13, $description, $categoryIds): Product {
            try {
                $statement = $this->pdo->prepare(
                    'UPDATE products SET name = :name, inn = :inn, ean13 = :ean13, description = :description, '
                    . 'updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                );
                $statement->execute([
                    'id' => $id,
                    'name' => $name,
                    'inn' => $inn,
                    'ean13' => $ean13,
                    'description' => $description,
                ]);
            } catch (PDOException $exception) {
                $this->throwIfUniqueViolation($exception);
                throw $exception;
            }

            $this->replaceCategories($id, $categoryIds);

            return $this->find($id) ?? throw new PDOException('Updated product cannot be loaded');
        });
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM products WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function existsByIdentity(string $inn, string $ean13, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM products WHERE inn = :inn AND ean13 = :ean13';
        $parameters = ['inn' => $inn, 'ean13' => $ean13];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :except_id';
            $parameters['except_id'] = $exceptId;
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    private function hydrate(array $row): Product
    {
        $statement = $this->pdo->prepare(
            'SELECT c.id, c.name FROM categories c '
            . 'INNER JOIN product_categories pc ON pc.category_id = c.id '
            . 'WHERE pc.product_id = :product_id ORDER BY c.id',
        );
        $statement->execute(['product_id' => $row['id']]);
        $categories = array_map(
            static fn (array $category): Category => new Category((int) $category['id'], (string) $category['name']),
            $statement->fetchAll(),
        );

        return new Product(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['inn'],
            (string) $row['ean13'],
            (string) $row['description'],
            $categories,
        );
    }

    /** @param list<int> $categoryIds */
    private function replaceCategories(int $productId, array $categoryIds): void
    {
        $delete = $this->pdo->prepare('DELETE FROM product_categories WHERE product_id = :product_id');
        $delete->execute(['product_id' => $productId]);

        $insert = $this->pdo->prepare(
            'INSERT INTO product_categories (product_id, category_id) VALUES (:product_id, :category_id)',
        );
        foreach ($categoryIds as $categoryId) {
            $insert->execute(['product_id' => $productId, 'category_id' => $categoryId]);
        }
    }

    private function transactional(callable $operation): Product
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $product = $operation();
            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return $product;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function throwIfUniqueViolation(PDOException $exception): void
    {
        if ($exception->getCode() === '23000' || str_contains(strtolower($exception->getMessage()), 'unique')) {
            throw new ConflictException('Product with this INN and EAN-13 already exists');
        }
    }
}
