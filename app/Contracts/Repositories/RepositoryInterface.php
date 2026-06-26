<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Core\Model;
use App\Core\QueryBuilder;

/**
 * Generic repository contract. All persistence flows through a repository
 * (docs/47 EAS-3); services depend on these interfaces, never on a concrete
 * implementation or on ad-hoc queries. Tenant scoping and soft-delete filtering
 * are applied by the underlying model.
 */
interface RepositoryInterface
{
    public function find(int $id): ?Model;

    public function findOrFail(int $id): Model;

    public function findByUuid(string $uuid): ?Model;

    /** @return Model[] */
    public function all(): array;

    /** @return array<string,mixed> Pagination payload with hydrated `data`. */
    public function paginate(int $perPage = 15, int $page = 1): array;

    public function create(array $attributes): Model;

    public function update(int $id, array $attributes): ?Model;

    public function delete(int $id): bool;

    public function restore(int $id): bool;

    /** Escape hatch for bespoke read queries (still tenant/soft-delete scoped). */
    public function query(): QueryBuilder;
}
