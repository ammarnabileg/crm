<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\CompanyRepositoryInterface;
use App\Models\Company;

/**
 * Repository for companies (tenants). Companies live in a global table; access is
 * always mediated through memberships, so cross-company lookups use the model's
 * unscoped builder explicitly.
 */
final class CompanyRepository extends BaseRepository implements CompanyRepositoryInterface
{
    protected string $model = Company::class;

    public function findBySlug(string $slug): ?Company
    {
        $row = Company::withoutTenantScope()->where('slug', '=', $slug)->first();

        return $row ? Company::hydrate($row) : null;
    }

    public function forUser(int $userId): array
    {
        return Company::withoutTenantScope()
            ->select('companies.*')
            ->join('memberships', 'memberships.company_id', '=', 'companies.id')
            ->where('memberships.user_id', '=', $userId)
            ->where('memberships.status', '=', 'active')
            ->orderBy('companies.created_at', 'desc')
            ->get();
    }
}
