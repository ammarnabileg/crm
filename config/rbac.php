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
     * The permission catalogue. Each entry is [key, name, module, description].
     * `module` is a system_modules.key (the module IS the permission group —
     * docs/database/12 R1-03); the enforced `action` is derived from the key
     * suffix. The canonical key remains `<module>.<action>`.
     */
    'permissions' => [
        // Dashboard
        ['dashboard.view', 'View dashboard', 'dashboard', 'Access the main dashboard.'],

        // Workspace profile
        ['workspace.view', 'View workspace', 'workspaces', 'View the current workspace profile.'],
        ['workspace.update', 'Edit workspace', 'workspaces', 'Edit the current workspace profile and settings.'],

        // Members
        ['members.view', 'View members', 'members', 'See the people in the workspace.'],
        ['members.invite', 'Invite members', 'members', 'Invite new people to the workspace.'],
        ['members.update', 'Edit members', 'members', 'Change member roles and details.'],
        ['members.remove', 'Remove members', 'members', 'Remove people from the workspace.'],

        // Roles
        ['roles.view', 'View roles', 'roles', 'View roles and their permissions.'],
        ['roles.manage', 'Manage roles', 'roles', 'Create, edit and delete roles.'],

        // Billing
        ['billing.view', 'View billing', 'billing', 'View subscription and invoices.'],
        ['billing.manage', 'Manage billing', 'billing', 'Change plans and manage the subscription.'],

        // AI
        ['ai.view', 'View AI settings', 'ai', 'View configured AI providers.'],
        ['ai.manage', 'Manage AI settings', 'ai', 'Add or update AI provider keys.'],

        // Settings
        ['settings.view', 'View settings', 'settings', 'View workspace settings.'],
        ['settings.manage', 'Manage settings', 'settings', 'Change workspace settings.'],

        // System operations (platform-level; held by super-admin only)
        ['system.manage', 'Manage system operations', 'system', 'Diagnostics, maintenance mode, backups/restore, environment editor and log viewer.'],

        // Recruitment / ATS
        ['recruitment.view', 'View recruitment', 'jobs', 'View jobs, the pipeline board, applications and the recruiter workspace.'],
        ['recruitment.manage', 'Manage recruitment', 'jobs', 'Create/edit jobs, move applications through stages, schedule interviews and manage offers.'],
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
                // The workspace Administrator runs recruitment day to day.
                'recruitment.view', 'recruitment.manage',
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
