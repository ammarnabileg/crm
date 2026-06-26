<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\Rbac\RbacManager;

/**
 * Seeds the baseline platform data: the permission catalogue, the global
 * super-admin role, and the initial subscription plan. Fully idempotent so the
 * installer can safely re-run it during recovery.
 *
 * Returned as an anonymous object (loaded via require, like migrations) so it
 * works during installation before any autoloaded seeder namespace exists.
 */
return new class {
    public function run(Database $db): void
    {
        $rbac = new RbacManager($db);
        $rbac->syncPermissions();
        $rbac->ensureSuperAdminRole();

        $this->seedDefaultPlan($db);
    }

    /**
     * The business currently sells a single plan (50 SAR / month). The schema
     * supports unlimited plans; this is just the first row.
     */
    private function seedDefaultPlan(Database $db): void
    {
        if ($db->table('plans')->where('slug', '=', 'standard')->exists()) {
            return;
        }

        $now = now();
        $db->table('plans')->insert([
            'name'        => 'Standard',
            'slug'        => 'standard',
            'description' => 'Everything a growing team needs to run on HalaOps.',
            'price'       => 50.00,
            'currency'    => 'SAR',
            'interval'    => 'monthly',
            'trial_days'  => 14,
            'features'    => json_encode([
                'ai_providers'   => true,
                'members'        => true,
                'roles'          => true,
                'activity_log'   => true,
            ], JSON_UNESCAPED_UNICODE),
            'limits'      => json_encode([
                'max_members' => 25,
            ], JSON_UNESCAPED_UNICODE),
            'is_active'   => 1,
            'is_public'   => 1,
            'sort_order'  => 1,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }
};
