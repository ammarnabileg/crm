<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Core\Database;
use App\Core\Model;
use App\Events\CompanyCreated;
use App\Models\Company;
use App\Models\User;
use App\Services\Rbac\RbacManager;

/**
 * Provisions a fully-formed tenant in one atomic operation.
 *
 * Creating a company sets up everything a tenant needs to function: the company
 * row, the creator's owner membership, the default role set (with the creator
 * assigned the Owner role), and a trial subscription on the default plan. Used
 * by self-service registration, the in-app "new company" flow, AND super-admin
 * provisioning — so the rules live in exactly one place.
 *
 * All writes use the raw connection with EXPLICIT company ids (never the tenant
 * scope) because we are creating a company that is, by definition, not yet the
 * active tenant.
 */
final class CompanyService
{
    private Database $db;

    public function __construct()
    {
        $this->db = app('db');
    }

    /**
     * @param User $owner The user who will own the company.
     */
    public function create(User $owner, string $name): Company
    {
        $name = trim($name);

        $company = $this->db->transaction(function (Database $db) use ($owner, $name): Company {
            $now = now();
            $ownerId = (int) $owner->getKey();

            $companyId = $db->table('companies')->insertGetId([
                'uuid'       => Model::generateUuid(),
                'name'       => $name,
                'slug'       => Company::uniqueSlug($name),
                'owner_id'   => $ownerId,
                'locale'     => (string) ($owner->getAttribute('locale') ?: 'en'),
                'status'     => 'trial',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Owner membership.
            $membershipId = $db->table('memberships')->insertGetId([
                'uuid'       => Model::generateUuid(),
                'company_id' => $companyId,
                'user_id'    => $ownerId,
                'status'     => 'active',
                'title'      => 'Owner',
                'joined_at'  => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Default roles for this company; assign Owner to the creator.
            $rbac = new RbacManager($db);
            $roles = $rbac->provisionCompanyRoles($companyId);
            if (isset($roles['owner'])) {
                $rbac->assignMembershipRole($membershipId, $roles['owner']);
            }

            $this->startTrialSubscription($db, $companyId);

            return Company::hydrate(
                $db->table('companies')->where('id', '=', $companyId)->first() ?? []
            );
        });

        // Side effects (audit trail, future onboarding/search indexing) run as
        // listeners once the company exists and is committed (docs/47 EAS-6).
        event(new CompanyCreated($company, $owner));

        return $company;
    }

    /**
     * Attach a trial subscription on the default (first active) plan, if any.
     */
    private function startTrialSubscription(Database $db, int $companyId): void
    {
        $plan = $db->table('plans')
            ->where('is_active', '=', 1)
            ->orderBy('sort_order')
            ->first();

        if ($plan === null) {
            return;
        }

        $now = now();
        $trialDays = (int) ($plan['trial_days'] ?? 0);

        $db->table('subscriptions')->insert([
            'uuid'          => Model::generateUuid(),
            'company_id'    => $companyId,
            'plan_id'       => (int) $plan['id'],
            'status'        => $trialDays > 0 ? 'trialing' : 'active',
            'amount'        => $plan['price'],
            'currency'      => $plan['currency'],
            'trial_ends_at' => $trialDays > 0 ? date('Y-m-d H:i:s', strtotime("+{$trialDays} days")) : null,
            'starts_at'     => $now,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }
}
