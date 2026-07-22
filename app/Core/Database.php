<?php

declare(strict_types=1);

namespace ParagonHostOps\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Thin PDO wrapper providing a shared connection and prepared-statement
 * helpers. All queries use bound parameters — never string concatenation.
 */
final class Database
{
    private ?PDO $pdo = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['host'],
            $this->config['port'],
            $this->config['database'],
            $this->config['charset']
        );

        try {
            $this->pdo = new PDO(
                $dsn,
                (string) $this->config['username'],
                (string) $this->config['password'],
                $this->config['options'] ?? []
            );
        } catch (PDOException $e) {
            // Do not leak credentials or DSN details to the caller.
            throw new RuntimeException('Database connection failed.', (int) $e->getCode());
        }

        return $this->pdo;
    }

    /**
     * @param array<string|int, mixed> $params
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return array<string, mixed>|null
     */
    public function first(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function all(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * @param array<string|int, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo()->lastInsertId();
    }
}
