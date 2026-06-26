<?php

declare(strict_types=1);

/**
 * RBAC definitions as DATA.
 *
 * Permissions and the default role templates live here, not in code. Adding a
 * capability is an edit to this file plus a re-seed — never an `if ($type ===
 * 'admin')`. Only permissions that are actually enforced somewhere in the app
 * are listed (the platform forbids unused permissions); domain modules add
 * their own permission groups as they ship.
 */

return [
    // The global super-admin role slug (assigned to users via user_role).
    'super_admin_role' => 'super-admin',

    /*
     * The permission catalogue, grouped for the role-editor UI. Each entry is
     * [key, name, group, description].
     */
    'permissions' => [
        // Dashboard
        ['dashboard.view', 'View dashboard', 'Dashboard', 'Access the main dashboard.'],

        // Company profile
        ['company.view', 'View company', 'Company', 'View the current company profile.'],
        ['company.update', 'Edit company', 'Company', 'Edit the current company profile and settings.'],

        // Members
        ['members.view', 'View members', 'Members', 'See the people in the company.'],
        ['members.invite', 'Invite members', 'Members', 'Invite new people to the company.'],
        ['members.update', 'Edit members', 'Members', 'Change member roles and details.'],
        ['members.remove', 'Remove members', 'Members', 'Remove people from the company.'],

        // Roles
        ['roles.view', 'View roles', 'Roles & Permissions', 'View roles and their permissions.'],
        ['roles.manage', 'Manage roles', 'Roles & Permissions', 'Create, edit and delete roles.'],

        // Billing
        ['billing.view', 'View billing', 'Billing', 'View subscription and invoices.'],
        ['billing.manage', 'Manage billing', 'Billing', 'Change plans and manage the subscription.'],

        // AI
        ['ai.view', 'View AI settings', 'AI', 'View configured AI providers.'],
        ['ai.manage', 'Manage AI settings', 'AI', 'Add or update AI provider keys.'],

        // Settings
        ['settings.view', 'View settings', 'Settings', 'View company settings.'],
        ['settings.manage', 'Manage settings', 'Settings', 'Change company settings.'],
    ],

    /*
     * Default roles created for every NEW company. The key '*' grants every
     * tenant permission (used for Owner). Roles may declare a `parent` slug for
     * inheritance. Adding HR/Recruiter/Candidate roles later is just data here,
     * once their domain permissions exist.
     */
    'tenant_roles' => [
        'owner' => [
            'name'        => 'Owner',
            'description' => 'Full control of the company, including billing and ownership.',
            'is_system'   => true,
            'priority'    => 100,
            'permissions' => '*',
        ],
        'admin' => [
            'name'        => 'Administrator',
            'description' => 'Manages the company day to day (no billing changes).',
            'is_system'   => true,
            'priority'    => 80,
            'permissions' => [
                'dashboard.view', 'company.view', 'company.update',
                'members.view', 'members.invite', 'members.update', 'members.remove',
                'roles.view', 'roles.manage', 'billing.view',
                'ai.view', 'ai.manage', 'settings.view', 'settings.manage',
            ],
        ],
        'member' => [
            'name'        => 'Member',
            'description' => 'A standard member of the company.',
            'is_system'   => true,
            'priority'    => 10,
            'permissions' => [
                'dashboard.view', 'company.view', 'members.view', 'ai.view', 'settings.view',
            ],
        ],
    ],
];
