<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Company;
use App\Models\User;

/**
 * Holds the active tenant (company) for the current request and is the single
 * authority the Model layer consults to scope every tenant-bound query.
 *
 * Isolation guarantee: a tenant-scoped model can only ever read/write rows for
 * the id this manager returns. Cross-tenant access requires the explicit
 * withoutTenantScope() escape hatch, which is confined to super-admin/system
 * code paths — there is no implicit way for one tenant to see another's data.
 */
final class TenantManager
{
    private ?int $id = null;
    private ?Company $company = null;
    private bool $booted = false;

    public function id(): ?int
    {
        return $this->id;
    }

    public function company(): ?Company
    {
        if ($this->company === null && $this->id !== null) {
            $this->company = Company::find($this->id);
        }

        return $this->company;
    }

    public function hasTenant(): bool
    {
        return $this->id !== null;
    }

    /**
     * Tenant scoping is always in force for tenant-bound models. Code that
     * legitimately needs to cross tenants must opt out via withoutTenantScope().
     */
    public function shouldScope(): bool
    {
        return true;
    }

    public function setTenant(Company $company): void
    {
        $this->id = (int) $company->getKey();
        $this->company = $company;
        session()->put((string) config('auth.tenant_key', 'active_company_id'), $this->id);
    }

    public function setById(int $companyId): bool
    {
        $company = Company::find($companyId);
        if ($company === null) {
            return false;
        }

        $this->setTenant($company);

        return true;
    }

    public function clear(): void
    {
        $this->id = null;
        $this->company = null;
        session()->forget((string) config('auth.tenant_key', 'active_company_id'));
    }

    /**
     * Establish the active tenant for an authenticated user.
     *
     * Preference order: the company stored in the session (if the user still
     * has an active membership there) -> the user's most recent company. A user
     * with no companies (e.g. a fresh super admin) simply has no active tenant
     * and operates platform-wide via global roles.
     */
    public function bootFor(User $user): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        $sessionKey = (string) config('auth.tenant_key', 'active_company_id');
        $stored = session()->get($sessionKey);

        if (is_numeric($stored) && $this->userBelongsTo($user, (int) $stored)) {
            $this->setById((int) $stored);

            return;
        }

        $companies = $user->companies();
        if ($companies !== []) {
            $this->setById((int) $companies[0]['id']);
        }
    }

    public function userBelongsTo(User $user, int $companyId): bool
    {
        $membership = $user->membershipFor($companyId);

        return $membership !== null && ($membership->status ?? '') === 'active';
    }
}
