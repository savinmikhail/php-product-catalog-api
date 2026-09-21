<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration;

use PDO;

final class PdoConnectionFactory
{
    public static function create(Config $config): PDO
    {
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
    }
}
