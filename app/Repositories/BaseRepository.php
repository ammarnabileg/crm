<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\RepositoryInterface;
use App\Core\Model;
use App\Core\QueryBuilder;

/**
 * Base repository: a thin, testable domain-facing layer over a Model.
 *
 * It centralizes persistence so callers depend on a contract, not on model
 * statics or raw SQL (docs/47 EAS-3). Tenant scoping, soft-delete filtering, and
 * UUID generation come from the model layer, so every repository inherits them.
 * Concrete repositories set $model and add domain-specific finders.
 */
abstract class BaseRepository implements RepositoryInterface
{
    /** @var class-string<Model> */
    protected string $model;

    public function find(int $id): ?Model
    {
        return ($this->model)::find($id);
    }

    public function findOrFail(int $id): Model
    {
        return ($this->model)::findOrFail($id);
    }

    public function findByUuid(string $uuid): ?Model
    {
        return ($this->model)::findByUuid($uuid);
    }

    /** @return Model[] */
    public function all(): array
    {
        return ($this->model)::all();
    }

    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $result = ($this->model)::query()->paginate($perPage, $page);
        $result['data'] = array_map([$this->model, 'hydrate'], $result['data']);

        return $result;
    }

    public function create(array $attributes): Model
    {
        return ($this->model)::create($attributes);
    }

    public function update(int $id, array $attributes): ?Model
    {
        $model = ($this->model)::find($id);
        if ($model === null) {
            return null;
        }

        $model->update($attributes);

        return $model;
    }

    public function delete(int $id): bool
    {
        $model = ($this->model)::find($id);

        return $model !== null && $model->delete();
    }

    public function forceDelete(int $id): bool
    {
        $key = ($this->model)::keyName();
        $row = ($this->model)::withTrashed()->where($key, '=', $id)->first();
        if ($row === null) {
            return false;
        }

        return ($this->model)::hydrate($row)->forceDelete();
    }

    public function restore(int $id): bool
    {
        $key = ($this->model)::keyName();
        $row = ($this->model)::withTrashed()->where($key, '=', $id)->first();
        if ($row === null) {
            return false;
        }

        return ($this->model)::hydrate($row)->restore();
    }

    public function query(): QueryBuilder
    {
        return ($this->model)::query();
    }
}
