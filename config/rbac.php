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

        // Workspace profile
        ['workspace.view', 'View workspace', 'Workspace', 'View the current workspace profile.'],
        ['workspace.update', 'Edit workspace', 'Workspace', 'Edit the current workspace profile and settings.'],

        // Members
        ['members.view', 'View members', 'Members', 'See the people in the workspace.'],
        ['members.invite', 'Invite members', 'Members', 'Invite new people to the workspace.'],
        ['members.update', 'Edit members', 'Members', 'Change member roles and details.'],
        ['members.remove', 'Remove members', 'Members', 'Remove people from the workspace.'],

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
        ['settings.view', 'View settings', 'Settings', 'View workspace settings.'],
        ['settings.manage', 'Manage settings', 'Settings', 'Change workspace settings.'],
    ],

    /*
     * Default roles created for every NEW workspace. The key '*' grants every
     * tenant permission (used for Owner). Roles may declare a `parent` slug for
     * inheritance. Adding HR/Recruiter/Candidate roles later is just data here,
     * once their domain permissions exist.
     */
    'tenant_roles' => [
        'owner' => [
            'name'        => 'Owner',
            'description' => 'Full control of the workspace, including billing and ownership.',
            'is_system'   => true,
            'priority'    => 100,
            'permissions' => '*',
        ],
        'admin' => [
            'name'        => 'Administrator',
            'description' => 'Manages the workspace day to day (no billing changes).',
            'is_system'   => true,
            'priority'    => 80,
            'permissions' => [
                'dashboard.view', 'workspace.view', 'workspace.update',
                'members.view', 'members.invite', 'members.update', 'members.remove',
                'roles.view', 'roles.manage', 'billing.view',
                'ai.view', 'ai.manage', 'settings.view', 'settings.manage',
            ],
        ],
        'member' => [
            'name'        => 'Member',
            'description' => 'A standard member of the workspace.',
            'is_system'   => true,
            'priority'    => 10,
            'permissions' => [
                'dashboard.view', 'workspace.view', 'members.view', 'ai.view', 'settings.view',
            ],
        ],
    ],
];
