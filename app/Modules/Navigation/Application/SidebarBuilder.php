<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Navigation\Application;

/**
 * Builds the single, dynamic sidebar from the current context, held permission
 * keys, and (optionally) the subscription's enabled features
 * (docs/SIDEBAR_MODEL.md, docs/NAVIGATION_ARCHITECTURE.md, docs/BILLING_PLATFORM.md §6).
 * There is no per-role sidebar; an item appears only if its permission is held
 * AND — when the item is feature-gated and features are supplied — its feature
 * is enabled by the plan.
 */
final class SidebarBuilder
{
    /**
     * Workspace-context items: [label, route, permission, group, feature?].
     * Ordered by group so the rendered menu reads as coherent sections
     * (docs/SIDEBAR_MODEL.md). The `group` label is a display heading only; it
     * never affects gating — an empty group (all items filtered out) renders no
     * heading.
     */
    private const WORKSPACE_ITEMS = [
        // Overview
        ['label' => 'Dashboard', 'route' => '/dashboard', 'permission' => 'workspace.view', 'group' => 'Overview'],
        ['label' => 'My Workspaces', 'route' => '/my-workspaces', 'permission' => 'workspace.view', 'group' => 'Overview'],
        // Recruiting
        ['label' => 'Jobs', 'route' => '/jobs', 'permission' => 'job.view', 'group' => 'Recruiting'],
        ['label' => 'Candidates', 'route' => '/candidates', 'permission' => 'candidate.view', 'group' => 'Recruiting'],
        ['label' => 'Pipeline', 'route' => '/pipeline', 'permission' => 'pipeline.view', 'group' => 'Recruiting'],
        ['label' => 'Talent Pool', 'route' => '/talent-pool', 'permission' => 'talent.view', 'group' => 'Recruiting'],
        ['label' => 'AI Interviews', 'route' => '/interviews', 'permission' => 'interview.view', 'group' => 'Recruiting'],
        ['label' => 'Human Interviews', 'route' => '/human-interviews', 'permission' => 'interview.view', 'group' => 'Recruiting'],
        ['label' => 'Offers', 'route' => '/offers', 'permission' => 'offer.view', 'group' => 'Recruiting'],
        ['label' => 'Avatars', 'route' => '/avatars', 'permission' => 'avatar.view', 'group' => 'Recruiting'],
        // Insights
        ['label' => 'Reports', 'route' => '/reports', 'permission' => 'report.view', 'group' => 'Insights'],
        ['label' => 'First Impression', 'route' => '/reports/first-impression', 'permission' => 'report.view', 'group' => 'Insights'],
        // Learning
        ['label' => 'Learning', 'route' => '/learning', 'permission' => 'learning.view', 'group' => 'Learning', 'feature' => 'learning'],
        ['label' => 'Learning Paths', 'route' => '/learning-paths', 'permission' => 'learning.view', 'group' => 'Learning', 'feature' => 'learning'],
        ['label' => 'My Learning', 'route' => '/my-learning', 'permission' => 'learning.view', 'group' => 'Learning', 'feature' => 'learning'],
        // Automation
        ['label' => 'Workflows', 'route' => '/workflows', 'permission' => 'workflow.view', 'group' => 'Automation', 'feature' => 'automation'],
        ['label' => 'Collections', 'route' => '/collections', 'permission' => 'workflow.view', 'group' => 'Automation', 'feature' => 'automation'],
        ['label' => 'Developer', 'route' => '/integrations', 'permission' => 'integration.view', 'group' => 'Automation', 'feature' => 'integrations'],
        // Team & Access
        ['label' => 'Members', 'route' => '/members', 'permission' => 'member.view', 'group' => 'Team & Access'],
        ['label' => 'Roles', 'route' => '/roles', 'permission' => 'role.view', 'group' => 'Team & Access'],
        ['label' => 'Activity', 'route' => '/activity', 'permission' => 'audit.view', 'group' => 'Team & Access'],
        // Tools
        ['label' => 'Tasks', 'route' => '/tasks', 'permission' => 'task.view', 'group' => 'Tools', 'feature' => 'tasks'],
        ['label' => 'Search', 'route' => '/search', 'permission' => 'search.use', 'group' => 'Tools'],
        ['label' => 'Files', 'route' => '/files', 'permission' => 'files.view', 'group' => 'Tools'],
        ['label' => 'AI', 'route' => '/ai', 'permission' => 'ai.view', 'group' => 'Tools', 'feature' => 'ai'],
        // Settings
        ['label' => 'Branding', 'route' => '/branding', 'permission' => 'workspace.branding', 'group' => 'Settings', 'feature' => 'white_label'],
        ['label' => 'Settings', 'route' => '/settings', 'permission' => 'settings.view', 'group' => 'Settings'],
        ['label' => 'Maintenance', 'route' => '/settings/maintenance', 'permission' => 'settings.update', 'group' => 'Settings'],
        ['label' => 'Billing', 'route' => '/billing', 'permission' => 'billing.view', 'group' => 'Settings'],
    ];

    /**
     * Candidate-context items. A candidate holds no role/permissions in the
     * workspace, so these are NOT permission-gated — they're the portal every
     * applicant sees (docs/SIDEBAR_MODEL.md: context decides the menu). A single
     * "My Space" group keeps the heading model uniform across contexts.
     */
    private const CANDIDATE_ITEMS = [
        ['label' => 'My Applications', 'route' => '/my-applications', 'permission' => '*', 'group' => 'My Space'],
        ['label' => 'Available Jobs', 'route' => '/open-jobs', 'permission' => '*', 'group' => 'My Space'],
        ['label' => 'My Insights', 'route' => '/my-insights', 'permission' => '*', 'group' => 'My Space'],
        ['label' => 'My Profile', 'route' => '/my-profile', 'permission' => '*', 'group' => 'My Space'],
    ];

    /** Platform-context items (System Owners): [label, route, permission, group]. */
    private const PLATFORM_ITEMS = [
        // Overview
        ['label' => 'Overview', 'route' => '/overview', 'permission' => 'system.dashboard.view', 'group' => 'Overview'],
        ['label' => 'Workspaces', 'route' => '/all-workspaces', 'permission' => 'system.workspaces.manage', 'group' => 'Overview'],
        ['label' => 'Users', 'route' => '/users', 'permission' => 'system.users.manage', 'group' => 'Overview'],
        // Access
        ['label' => 'Roles & Permissions', 'route' => '/permissions', 'permission' => 'system.roles.manage', 'group' => 'Access'],
        // Commerce
        ['label' => 'Subscriptions', 'route' => '/subscriptions', 'permission' => 'system.subscriptions.manage', 'group' => 'Commerce'],
        ['label' => 'Plans', 'route' => '/plans', 'permission' => 'system.subscriptions.manage', 'group' => 'Commerce'],
        ['label' => 'Pricing', 'route' => '/pricing', 'permission' => 'system.pricing.manage', 'group' => 'Commerce'],
        ['label' => 'Payments', 'route' => '/payments', 'permission' => 'system.subscriptions.manage', 'group' => 'Commerce'],
        // System
        ['label' => 'AI Providers', 'route' => '/ai-providers', 'permission' => 'system.ai.manage', 'group' => 'System'],
        ['label' => 'Audit Logs', 'route' => '/audit-log', 'permission' => 'system.audit.view', 'group' => 'System'],
        ['label' => 'Diagnostics', 'route' => '/diagnostics', 'permission' => 'system.diagnostics.run', 'group' => 'System'],
        ['label' => 'Platform Settings', 'route' => '/platform-settings', 'permission' => 'system.settings.manage', 'group' => 'System'],
    ];

    /**
     * @param  list<string>  $heldPermissionKeys
     * @param  list<string>|null  $enabledFeatures  null = do not feature-gate
     * @return list<array{label: string, route: string, permission: string, group: string}>
     */
    public function build(string $context, array $heldPermissionKeys, ?array $enabledFeatures = null): array
    {
        // The candidate menu is context-driven, not permission-gated.
        if ($context === 'candidate') {
            return array_map(
                static fn (array $i): array => [
                    'label' => $i['label'],
                    'route' => $i['route'],
                    'permission' => $i['permission'],
                    'group' => $i['group'],
                ],
                self::CANDIDATE_ITEMS,
            );
        }

        $items = $context === 'platform' ? self::PLATFORM_ITEMS : self::WORKSPACE_ITEMS;
        $held = array_flip($heldPermissionKeys);
        $features = $enabledFeatures !== null ? array_flip($enabledFeatures) : null;

        $visible = array_filter(
            $items,
            static function (array $item) use ($held, $features): bool {
                if (! isset($held[$item['permission']])) {
                    return false;
                }

                // Feature-gated item: hide unless its feature is enabled by the plan.
                if ($features !== null && isset($item['feature']) && ! isset($features[$item['feature']])) {
                    return false;
                }

                return true;
            },
        );

        // Project down to the public shape (drop the internal `feature` key). The
        // `group` heading survives so a filtered-empty group simply never appears.
        return array_values(array_map(
            static fn (array $i): array => [
                'label' => $i['label'],
                'route' => $i['route'],
                'permission' => $i['permission'],
                'group' => $i['group'],
            ],
            $visible,
        ));
    }

    /**
     * @param  list<string>  $heldPermissionKeys
     * @param  list<string>|null  $enabledFeatures
     * @return list<string>  just the visible labels (handy for assertions/UI)
     */
    public function labels(string $context, array $heldPermissionKeys, ?array $enabledFeatures = null): array
    {
        return array_map(static fn (array $i): string => $i['label'], $this->build($context, $heldPermissionKeys, $enabledFeatures));
    }

    /**
     * Every permission key referenced by the sidebar (both contexts). Used by the
     * release audit to assert no key has drifted from the PermissionCatalog.
     *
     * @return list<string>
     */
    public static function allPermissionKeys(): array
    {
        $keys = [];
        foreach ([...self::WORKSPACE_ITEMS, ...self::PLATFORM_ITEMS] as $item) {
            $keys[] = $item['permission'];
        }

        return array_values(array_unique($keys));
    }
}
