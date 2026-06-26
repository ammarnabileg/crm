<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Core\Database;
use App\Core\Model;
use App\Events\WorkspaceCreated;
use App\Models\Workspace;
use App\Models\User;
use App\Services\Rbac\RbacManager;

/**
 * Provisions a fully-formed tenant in one atomic operation.
 *
 * Creating a workspace sets up everything a tenant needs to function: the workspace
 * row, the creator's owner membership, the default role set (with the creator
 * assigned the Owner role), and a trial subscription on the default plan. Used
 * by self-service registration, the in-app "new workspace" flow, AND super-admin
 * provisioning — so the rules live in exactly one place.
 *
 * All writes use the raw connection with EXPLICIT workspace ids (never the tenant
 * scope) because we are creating a workspace that is, by definition, not yet the
 * active tenant.
 */
final class WorkspaceService
{
    private Database $db;

    public function __construct()
    {
        $this->db = app('db');
    }

    /**
     * @param User $owner The user who will own the workspace.
     */
    public function create(User $owner, string $name): Workspace
    {
        $name = trim($name);

        $workspace = $this->db->transaction(function (Database $db) use ($owner, $name): Workspace {
            $now = now();
            $ownerId = (int) $owner->getKey();

            $workspaceId = $db->table('workspaces')->insertGetId([
                'uuid'              => Model::generateUuid(),
                'workspace_type_id' => $this->defaultWorkspaceTypeId($db),
                'name'              => $name,
                'slug'              => Workspace::uniqueSlug($name),
                'owner_id'          => $ownerId,
                'locale'            => (string) ($owner->getAttribute('locale') ?: 'en'),
                'workspace_status_id' => status_id('workspace_statuses', 'trial'),
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);

            // Owner membership.
            $membershipId = $db->table('memberships')->insertGetId([
                'uuid'       => Model::generateUuid(),
                'workspace_id' => $workspaceId,
                'user_id'    => $ownerId,
                'membership_status_id' => lookup_id('membership_status', 'active'),
                'title'      => 'Owner',
                'joined_at'  => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Default roles for this workspace; assign Owner to the creator.
            $rbac = new RbacManager($db);
            $roles = $rbac->provisionWorkspaceRoles($workspaceId);
            if (isset($roles['owner'])) {
                $rbac->assignMembershipRole($membershipId, $roles['owner']);
            }

            $this->startTrialSubscription($db, $workspaceId);

            return Workspace::hydrate(
                $db->table('workspaces')->where('id', '=', $workspaceId)->first() ?? []
            );
        });

        // Side effects (audit trail, future onboarding/search indexing) run as
        // listeners once the workspace exists and is committed (docs/47 EAS-6).
        event(new WorkspaceCreated($workspace, $owner));

        return $workspace;
    }

    /**
     * The default workspace type id (the seeded "company" type). Workspaces are
     * typed so the same architecture serves any organisation kind (docs/database
     * Revision R1); self-service creation defaults to a company.
     */
    private function defaultWorkspaceTypeId(Database $db): int
    {
        $id = $db->table('workspace_types')->where('key', '=', 'company')->value('id')
            ?? $db->table('workspace_types')->orderBy('sort_order')->value('id');

        return (int) $id;
    }

    /**
     * Attach a trial subscription on the default (first active) plan, if any.
     */
    private function startTrialSubscription(Database $db, int $workspaceId): void
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
            'workspace_id'    => $workspaceId,
            'plan_id'       => (int) $plan['id'],
            'subscription_status_id' => status_id('subscription_statuses', $trialDays > 0 ? 'trialing' : 'active'),
            'amount'        => $plan['price'],
            'currency'      => $plan['currency'],
            'trial_ends_at' => $trialDays > 0 ? date('Y-m-d H:i:s', strtotime("+{$trialDays} days")) : null,
            'starts_at'     => $now,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }
}
