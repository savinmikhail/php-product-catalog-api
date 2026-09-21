<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Domain\Category;
use App\Modules\Catalog\Port\CategoryRepository;
use App\Shared\Error\ConflictException;
use PDO;
use PDOException;

final class PdoCategoryRepository implements CategoryRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        $statement = $this->pdo->query('SELECT id, name FROM categories ORDER BY id');

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    public function find(int $id): ?Category
    {
        $statement = $this->pdo->prepare('SELECT id, name FROM categories WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function create(string $name): Category
    {
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO categories (name, created_at, updated_at) VALUES (:name, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            );
            $statement->execute(['name' => $name]);
        } catch (PDOException $exception) {
            $this->throwIfUniqueViolation($exception);
            throw $exception;
        }

        return $this->find((int) $this->pdo->lastInsertId()) ?? throw new PDOException('Created category cannot be loaded');
    }

    public function update(int $id, string $name): Category
    {
        try {
            $statement = $this->pdo->prepare(
                'UPDATE categories SET name = :name, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            );
            $statement->execute(['id' => $id, 'name' => $name]);
        } catch (PDOException $exception) {
            $this->throwIfUniqueViolation($exception);
            throw $exception;
        }

        return $this->find($id) ?? throw new PDOException('Updated category cannot be loaded');
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM categories WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function exists(int $id): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM categories WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->fetchColumn() !== false;
    }

    public function existsByName(string $name, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM categories WHERE name = :name';
        $parameters = ['name' => $name];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :except_id';
            $parameters['except_id'] = $exceptId;
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    private function hydrate(array $row): Category
    {
        return new Category((int) $row['id'], (string) $row['name']);
    }

    private function throwIfUniqueViolation(PDOException $exception): void
    {
        if ($exception->getCode() === '23000' || str_contains(strtolower($exception->getMessage()), 'unique')) {
            throw new ConflictException('Category name must be unique');
        }
    }
}
