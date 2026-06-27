<?php

declare(strict_types=1);

namespace HaHireAI\Core\Database;

use HaHireAI\Core\Database\Exceptions\QueryException;
use PDO;
use PDOException;
use Throwable;

/**
 * A thin PDO wrapper. Every query uses prepared statements (no string-concat
 * SQL — Constitution §6). No ORM. See docs/DATABASE_ARCHITECTURE.md.
 */
final class Connection
{
    private ?PDO $pdo = null;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= $this->connect();
    }

    /**
     * Run a SELECT and return all rows.
     *
     * @param  array<string|int, mixed>  $bindings
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings, function (string $sql, array $bindings): array {
            $statement = $this->pdo()->prepare($sql);
            $statement->execute($bindings);

            /** @var list<array<string, mixed>> $rows */
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

            return $rows;
        });
    }

    /**
     * Run a SELECT and return the first row (or null).
     *
     * @param  array<string|int, mixed>  $bindings
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        return $this->select($sql, $bindings)[0] ?? null;
    }

    /**
     * Run an INSERT/UPDATE/DELETE/DDL statement; returns affected row count.
     *
     * @param  array<string|int, mixed>  $bindings
     */
    public function statement(string $sql, array $bindings = []): int
    {
        return $this->run($sql, $bindings, function (string $sql, array $bindings): int {
            $statement = $this->pdo()->prepare($sql);
            $statement->execute($bindings);

            return $statement->rowCount();
        });
    }

    /** Execute raw DDL (no bindings), e.g. CREATE TABLE. */
    public function unprepared(string $sql): bool
    {
        return $this->run($sql, [], fn (string $sql): bool => $this->pdo()->exec($sql) !== false);
    }

    /** Run a callback inside a transaction, rolling back on any exception. */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $result = $callback($this);
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function lastInsertId(): string
    {
        return $this->pdo()->lastInsertId();
    }

    private function connect(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) ($this->config['host'] ?? '127.0.0.1'),
            (int) ($this->config['port'] ?? 3306),
            (string) ($this->config['database'] ?? ''),
            (string) ($this->config['charset'] ?? 'utf8mb4'),
        );

        try {
            return new PDO(
                $dsn,
                (string) ($this->config['username'] ?? 'root'),
                (string) ($this->config['password'] ?? ''),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ],
            );
        } catch (PDOException $e) {
            throw new QueryException('Database connection failed: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * @param  array<string|int, mixed>  $bindings
     */
    private function run(string $sql, array $bindings, callable $callback): mixed
    {
        try {
            return $callback($sql, $bindings);
        } catch (PDOException $e) {
            throw new QueryException(
                'Query failed: ' . $e->getMessage() . ' [SQL: ' . $sql . ']',
                previous: $e,
            );
        }
    }
}
