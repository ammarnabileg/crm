<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Presentation;

use HaHireAI\Core\Contracts\EntitlementResolver;
use HaHireAI\Core\Contracts\NotificationFeed;
use HaHireAI\Core\Contracts\SupportInfo;
use HaHireAI\Core\Contracts\WorkspaceAllowance;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Navigation\Application\ContextSwitcher;
use HaHireAI\Modules\Navigation\Application\SidebarBuilder;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Application\WorkspacePreferences;

/**
 * Renders a page inside the authenticated workspace shell (top bar + the single
 * dynamic sidebar built from the current member's permissions and the plan's
 * enabled features). Keeps controllers free of layout plumbing.
 */
final class WorkspaceShell
{
    public function __construct(
        private readonly View $view,
        private readonly SidebarBuilder $sidebar,
        private readonly AuthContext $auth,
        private readonly EntitlementResolver $entitlements,
        private readonly WorkspacePreferences $preferences,
        private readonly WorkspaceAllowance $allowance,
        private readonly SupportInfo $support,
        private readonly NotificationFeed $notifications,
        private readonly ContextSwitcher $switcher,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{bypassGate?: bool}  $options  bypassGate skips the
     *         suspended/maintenance gates for account-level pages (e.g. "My
     *         Workspaces") that must stay reachable even when the current
     *         workspace is paused.
     */
    public function render(WorkspaceContext $context, string $page, array $data = [], array $options = []): Response
    {
        $workspaceId = $context->workspaceId();
        $workspace = $context->workspace();
        $bypassGate = ($options['bypassGate'] ?? false) === true;

        // Paused: the workspace was stopped by the platform (System Owner),
        // paused by its owner (cap/archive), or its owner's plan lapsed/was
        // suspended (non-renewal). Members see a "service paused" screen with a
        // way to reach support.
        $ownerId = (string) ($workspace['owner_user_id'] ?? '');
        $status = (string) ($workspace['status'] ?? 'active');
        $reason = null;
        if (! $bypassGate && $workspace !== null) {
            if ($status === 'suspended') {
                $reason = 'suspended';
            } elseif ($status === 'archived') {
                $reason = 'archived';
            } elseif ($ownerId !== '' && ! $this->allowance->isUsable($ownerId)) {
                $reason = 'plan';
            }
        }
        if ($reason !== null) {
            return Response::html($this->view->page('workspace.suspended', [
                'workspaceName' => $workspace['name'] ?? null,
                'support' => $this->support->support(),
                'reason' => $reason,
            ], 'layouts.guest', ['title' => 'Service paused']), 503);
        }

        // Maintenance mode: pause the workspace for everyone except admins
        // (members who can change settings) and explicitly allow-listed IPs.
        // Settings stays reachable to toggle it off.
        if ($workspaceId !== null
            && $this->preferences->bool($workspaceId, 'maintenance.enabled')
            && ! $context->can('settings.update')
            && ! $this->ipAllowed($workspaceId)) {
            return Response::html($this->view->page('workspace.maintenance', [
                'workspaceName' => $context->workspace()['name'] ?? null,
                'message' => $this->preferences->get($workspaceId, 'maintenance.message', '') ?: 'This workspace is temporarily paused for maintenance.',
            ], 'layouts.app', [
                'user' => $this->auth->user(),
                'sidebar' => [],
                'workspaceName' => $context->workspace()['name'] ?? null,
            ]), 503);
        }

        $features = $workspaceId !== null ? $this->entitlements->gateFeatures($workspaceId) : null;

        $wsId = (string) $context->workspaceId();
        $uId = (string) $context->userId();
        $html = $this->view->page($page, $data, 'layouts.app', [
            'user' => $this->auth->user(),
            'sidebar' => $this->sidebar->build('workspace', $context->permissions(), $features),
            'workspaceName' => $context->workspace()['name'] ?? null,
            'workspaces' => $context->workspaces(),
            'currentWorkspaceId' => $context->workspaceId(),
            'notifications' => $wsId !== '' && $uId !== '' ? $this->notifications->recentForUser($wsId, $uId) : [],
            'unreadCount' => $wsId !== '' && $uId !== '' ? $this->notifications->unreadCount($wsId, $uId) : 0,
            'switcher' => $this->switcher->model('staff'),
            'fullBleed' => ($options['fullBleed'] ?? false) === true,
        ]);

        return Response::html($html);
    }

    /** True if the caller's IP is on the maintenance allow-list. */
    private function ipAllowed(string $workspaceId): bool
    {
        $list = trim((string) $this->preferences->get($workspaceId, 'maintenance.allow_ips', ''));
        if ($list === '') {
            return false;
        }
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return $ip !== '' && in_array($ip, array_map('trim', explode(',', $list)), true);
    }
}
