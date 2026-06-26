<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Thin PDO wrapper. Owns a single lazily-established MySQL connection and
 * exposes safe, prepared-statement query helpers plus a transaction runner.
 * All higher-level data access (QueryBuilder, Model) flows through here, so
 * there is exactly one place where SQL meets the driver.
 */
final class Database
{
    private ?PDO $pdo = null;

    private int $transactionLevel = 0;

    public function __construct(private readonly array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->connect();
        }

        return $this->pdo;
    }

    private function connect(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $this->config['host'] ?? '127.0.0.1',
            $this->config['port'] ?? '3306',
            $this->config['database'] ?? '',
            $this->config['charset'] ?? 'utf8mb4'
        );

        try {
            $pdo = new PDO(
                $dsn,
                $this->config['username'] ?? '',
                $this->config['password'] ?? '',
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ]
            );
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }

        $collation = $this->config['collation'] ?? 'utf8mb4_unicode_ci';
        $pdo->exec("SET NAMES '" . ($this->config['charset'] ?? 'utf8mb4') . "' COLLATE '{$collation}'");
        $pdo->exec("SET sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");

        return $pdo;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        $statement = $this->run($sql, $bindings);

        return $statement->fetchAll();
    }

    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $statement = $this->run($sql, $bindings);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function scalar(string $sql, array $bindings = []): mixed
    {
        $statement = $this->run($sql, $bindings);

        return $statement->fetchColumn();
    }

    public function insert(string $sql, array $bindings = []): string
    {
        $this->run($sql, $bindings);

        return $this->pdo()->lastInsertId();
    }

    public function affectingStatement(string $sql, array $bindings = []): int
    {
        return $this->run($sql, $bindings)->rowCount();
    }

    public function statement(string $sql, array $bindings = []): bool
    {
        return $this->run($sql, $bindings) !== null;
    }

    public function unprepared(string $sql): bool
    {
        return $this->pdo()->exec($sql) !== false;
    }

    private function run(string $sql, array $bindings): \PDOStatement
    {
        try {
            $statement = $this->pdo()->prepare($sql);
            $statement->execute($this->normalizeBindings($bindings));

            return $statement;
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Query failed: ' . $e->getMessage() . ' [SQL: ' . $sql . ']',
                (int) $e->getCode(),
                $e
            );
        }
    }

    private function normalizeBindings(array $bindings): array
    {
        $normalized = [];
        foreach ($bindings as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 1 : 0;
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * Run a callback inside a transaction with nested savepoint support.
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function beginTransaction(): void
    {
        if ($this->transactionLevel === 0) {
            $this->pdo()->beginTransaction();
        } else {
            $this->pdo()->exec('SAVEPOINT trans' . ($this->transactionLevel + 1));
        }
        $this->transactionLevel++;
    }

    public function commit(): void
    {
        if ($this->transactionLevel === 1) {
            $this->pdo()->commit();
        }
        $this->transactionLevel = max(0, $this->transactionLevel - 1);
    }

    public function rollBack(): void
    {
        if ($this->transactionLevel <= 1) {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }
            $this->transactionLevel = 0;
        } else {
            $this->pdo()->exec('ROLLBACK TO SAVEPOINT trans' . $this->transactionLevel);
            $this->transactionLevel--;
        }
    }

    public function inTransactionDepth(): int
    {
        return $this->transactionLevel;
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }
}
