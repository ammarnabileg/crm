<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Permissions\Domain;

/**
 * The canonical permission catalog as data (docs/PERMISSION_CATALOG.md).
 * Modules contribute their keys here; the seeder writes them to the
 * `permissions` table. Code checks KEYS, never role names.
 */
final class PermissionCatalog
{
    /**
     * @return list<array{key: string, category: string, system: bool, description: string}>
     */
    public static function all(): array
    {
        $workspace = [
            'Workspace' => [
                'workspace.view' => 'View workspace overview',
                'workspace.update' => 'Edit workspace core fields',
                'workspace.settings' => 'Manage workspace settings',
                'workspace.branding' => 'Manage branding',
                'workspace.archive' => 'Archive the workspace',
                'workspace.restore' => 'Restore an archived workspace',
                'workspace.delete' => 'Soft-delete the workspace',
                'workspace.transfer' => 'Transfer ownership',
            ],
            'Members' => [
                'member.view' => 'View members',
                'member.invite' => 'Invite members',
                'member.update' => 'Update a member',
                'member.suspend' => 'Suspend a member',
                'member.reactivate' => 'Reactivate a member',
                'member.remove' => 'Remove a member',
            ],
            'Roles' => [
                'role.view' => 'View roles',
                'role.create' => 'Create a role',
                'role.update' => 'Edit a role',
                'role.clone' => 'Clone a role',
                'role.delete' => 'Delete a role',
                'permission.assign' => 'Assign permissions to roles/members',
            ],
            'Jobs' => [
                'job.view' => 'View jobs',
                'job.create' => 'Create a job',
                'job.update' => 'Edit a job',
                'job.publish' => 'Publish a job',
                'job.archive' => 'Archive a job',
                'job.delete' => 'Delete a job',
            ],
            'Candidates' => [
                'candidate.view' => 'View candidate profiles',
                'candidate.note' => 'Add/view notes',
                'candidate.tag' => 'Apply tags',
                'candidate.export' => 'Export candidate data',
            ],
            'Talent' => [
                'talent.view' => 'View talent pools',
                'talent.manage' => 'Create pools and save candidates',
            ],
            'Tasks' => [
                'task.view' => 'View tasks',
                'task.manage' => 'Create, assign and complete tasks',
            ],
            'Applications' => [
                'application.view' => 'View applications',
                'application.update' => 'Update an application',
                'pipeline.view' => 'View the pipeline',
                'pipeline.manage' => 'Move stages / bulk actions',
            ],
            'Interviews' => [
                'interview.view' => 'View interviews',
                'interview.schedule' => 'Schedule interviews',
                'interview.ai.run' => 'Run an AI interview',
                'interview.evaluate' => 'Submit evaluations',
            ],
            'Avatars' => [
                'avatar.view' => 'View AI interviewer avatars',
                'avatar.manage' => 'Create and edit avatars',
            ],
            'Offers' => [
                'offer.view' => 'View offers',
                'offer.create' => 'Create an offer',
                'offer.send' => 'Send an offer',
            ],
            'Reports' => [
                'report.view' => 'View reports',
                'report.export' => 'Export reports',
            ],
            'Files' => [
                'files.view' => 'View files',
                'files.upload' => 'Upload files',
                'files.delete' => 'Delete files',
            ],
            'AI' => [
                'ai.view' => 'View AI settings/usage',
                'ai.run' => 'Invoke AI capabilities',
                'ai.configure' => 'Configure provider/model',
                'ai.keys.manage' => 'Manage workspace API keys',
            ],
            'Billing' => [
                'billing.view' => 'View billing',
                'billing.manage' => 'Manage plan/payment methods',
            ],
            'Settings' => [
                'settings.view' => 'View settings',
                'settings.update' => 'Update settings',
            ],
            'Audit' => [
                'audit.view' => 'View workspace audit log',
            ],
            'Search' => [
                'search.use' => 'Use unified workspace search',
            ],
            'Workflow' => [
                'workflow.view' => 'View workflows & executions',
                'workflow.create' => 'Create a workflow',
                'workflow.update' => 'Edit a workflow',
                'workflow.delete' => 'Delete a workflow',
                'workflow.execute' => 'Manually run a workflow',
                'workflow.publish' => 'Publish or unpublish a workflow',
                'workflow.pause' => 'Pause or resume a workflow',
                'workflow.logs' => 'View workflow execution logs',
                'workflow.templates' => 'Use & manage workflow templates',
                'workflow.variables' => 'Manage workflow variables & dynamic collections',
                'workflow.settings' => 'Manage workflow settings',
            ],
            'Integration' => [
                'integration.view' => 'View the developer portal (API tokens, webhooks)',
                'api.tokens.manage' => 'Issue and revoke API tokens',
                'webhook.manage' => 'Manage outbound webhook endpoints',
            ],
            'Notifications' => [
                'notification.view' => 'View personal notifications',
            ],
        ];

        $system = [
            'system.dashboard.view' => 'Access the Platform Context',
            'system.users.manage' => 'Manage all users',
            'system.roles.manage' => 'Manage platform roles & permissions',
            'system.workspaces.manage' => 'Manage all workspaces',
            'system.subscriptions.manage' => 'Manage subscriptions',
            'system.settings.manage' => 'Manage platform settings',
            'system.ai.manage' => 'Manage global AI providers',
            'system.diagnostics.run' => 'Run diagnostics',
            'system.observability.view' => 'View platform metrics/logs',
            'system.audit.view' => 'View platform audit log',
        ];

        $out = [];

        foreach ($workspace as $category => $keys) {
            foreach ($keys as $key => $description) {
                $out[] = ['key' => $key, 'category' => $category, 'system' => false, 'description' => $description];
            }
        }

        foreach ($system as $key => $description) {
            $out[] = ['key' => $key, 'category' => 'System', 'system' => true, 'description' => $description];
        }

        return $out;
    }

    /** Just the workspace-scoped permission keys (e.g. for granting a new owner). */
    public static function workspaceKeys(): array
    {
        return array_values(array_map(
            static fn (array $p): string => $p['key'],
            array_filter(self::all(), static fn (array $p): bool => $p['system'] === false),
        ));
    }
}
