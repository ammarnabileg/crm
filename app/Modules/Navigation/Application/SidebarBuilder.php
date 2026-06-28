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
    /** Workspace-context items: [label, route, permission, feature?]. */
    private const WORKSPACE_ITEMS = [
        ['label' => 'Dashboard', 'route' => '/dashboard', 'permission' => 'workspace.view'],
        ['label' => 'Jobs', 'route' => '/jobs', 'permission' => 'job.view'],
        ['label' => 'Candidates', 'route' => '/candidates', 'permission' => 'candidate.view'],
        ['label' => 'Pipeline', 'route' => '/pipeline', 'permission' => 'pipeline.view'],
        ['label' => 'Interviews', 'route' => '/interviews', 'permission' => 'interview.view'],
        ['label' => 'Offers', 'route' => '/offers', 'permission' => 'offer.view'],
        ['label' => 'Reports', 'route' => '/reports', 'permission' => 'report.view'],
        ['label' => 'Members', 'route' => '/members', 'permission' => 'member.view'],
        ['label' => 'Roles', 'route' => '/roles', 'permission' => 'role.view'],
        ['label' => 'Notifications', 'route' => '/notifications', 'permission' => 'notification.view'],
        ['label' => 'Activity', 'route' => '/activity', 'permission' => 'audit.view'],
        ['label' => 'AI', 'route' => '/ai', 'permission' => 'ai.view', 'feature' => 'ai'],
        ['label' => 'Workflows', 'route' => '/workflows', 'permission' => 'workflow.view', 'feature' => 'automation'],
        ['label' => 'Developer', 'route' => '/integrations', 'permission' => 'integration.view', 'feature' => 'integrations'],
        ['label' => 'Search', 'route' => '/search', 'permission' => 'search.use'],
        ['label' => 'Files', 'route' => '/files', 'permission' => 'files.view'],
        ['label' => 'Settings', 'route' => '/settings', 'permission' => 'settings.view'],
        ['label' => 'Billing', 'route' => '/billing', 'permission' => 'billing.view'],
    ];

    /** Platform-context items (System Owners): [label, route, permission]. */
    private const PLATFORM_ITEMS = [
        ['label' => 'Overview', 'route' => '/admin', 'permission' => 'system.dashboard.view'],
        ['label' => 'Workspaces', 'route' => '/admin/workspaces', 'permission' => 'system.workspaces.manage'],
        ['label' => 'Users', 'route' => '/admin/users', 'permission' => 'system.users.manage'],
        ['label' => 'Subscriptions', 'route' => '/admin/subscriptions', 'permission' => 'system.subscriptions.manage'],
        ['label' => 'AI Providers', 'route' => '/admin/ai', 'permission' => 'system.ai.manage'],
        ['label' => 'Audit Logs', 'route' => '/admin/audit', 'permission' => 'system.audit.view'],
        ['label' => 'Diagnostics', 'route' => '/admin/diagnostics', 'permission' => 'system.diagnostics.run'],
        ['label' => 'Platform Settings', 'route' => '/admin/settings', 'permission' => 'system.settings.manage'],
    ];

    /**
     * @param  list<string>  $heldPermissionKeys
     * @param  list<string>|null  $enabledFeatures  null = do not feature-gate
     * @return list<array{label: string, route: string, permission: string}>
     */
    public function build(string $context, array $heldPermissionKeys, ?array $enabledFeatures = null): array
    {
        $items = $context === 'platform' ? self::PLATFORM_ITEMS : self::WORKSPACE_ITEMS;
        $held = array_flip($heldPermissionKeys);
        $features = $enabledFeatures !== null ? array_flip($enabledFeatures) : null;

        return array_values(array_filter(
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
