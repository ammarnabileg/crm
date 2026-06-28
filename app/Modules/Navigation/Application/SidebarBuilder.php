<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Navigation\Application;

/**
 * Builds the single, dynamic sidebar from the current context + held permission
 * keys (docs/SIDEBAR_MODEL.md, docs/NAVIGATION_ARCHITECTURE.md). There is no
 * per-role sidebar; an item appears only if its permission is held (and, later,
 * if its module is enabled by the subscription).
 */
final class SidebarBuilder
{
    /** Workspace-context items: [label, route, permission]. */
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
        ['label' => 'Activity', 'route' => '/activity', 'permission' => 'audit.view'],
        ['label' => 'AI', 'route' => '/ai', 'permission' => 'ai.view'],
        ['label' => 'Workflows', 'route' => '/workflows', 'permission' => 'workflow.view'],
        ['label' => 'Developer', 'route' => '/integrations', 'permission' => 'integration.view'],
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
     * @return list<array{label: string, route: string, permission: string}>
     */
    public function build(string $context, array $heldPermissionKeys): array
    {
        $items = $context === 'platform' ? self::PLATFORM_ITEMS : self::WORKSPACE_ITEMS;
        $held = array_flip($heldPermissionKeys);

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => isset($held[$item['permission']]),
        ));
    }

    /**
     * @param  list<string>  $heldPermissionKeys
     * @return list<string>  just the visible labels (handy for assertions/UI)
     */
    public function labels(string $context, array $heldPermissionKeys): array
    {
        return array_map(static fn (array $i): string => $i['label'], $this->build($context, $heldPermissionKeys));
    }
}
