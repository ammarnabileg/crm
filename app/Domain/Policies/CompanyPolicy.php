<?php

declare(strict_types=1);

namespace App\Domain\Policies;

use App\Models\Company;
use App\Models\User;
use App\Services\Rbac\AccessControl;
use App\Services\Tenancy\TenantManager;

/**
 * Authorization policy for Company objects. Encapsulates context-aware rules
 * that a permission flag alone cannot express — ownership and tenant membership
 * (docs/47 EAS-11). Super admins are short-circuited by AccessControl before a
 * policy runs, so these methods only decide for ordinary users.
 */
final class CompanyPolicy
{
    public function __construct(
        private readonly AccessControl $access,
        private readonly TenantManager $tenant,
    ) {
    }

    /** A user may view a company they actively belong to. */
    public function view(User $user, Company $company): bool
    {
        return $user->membershipFor((int) $company->getKey()) !== null;
    }

    /**
     * A user may edit a company if they own it, or hold company.update within
     * that company's active context.
     */
    public function update(User $user, Company $company): bool
    {
        if ($user->ownsCompany((int) $company->getKey())) {
            return true;
        }

        return $this->tenant->id() === (int) $company->getKey()
            && $this->access->hasPermission($user, 'company.update');
    }

    /** Only the owner may delete (archive) a company. */
    public function delete(User $user, Company $company): bool
    {
        return $user->ownsCompany((int) $company->getKey());
    }
}
