<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Presentation;

use HaHireAI\Core\Contracts\EntitlementResolver;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Authentication\Application\AuthContext;
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
    ) {
    }

    /** @param array<string, mixed> $data */
    public function render(WorkspaceContext $context, string $page, array $data = []): Response
    {
        $workspaceId = $context->workspaceId();

        // Maintenance mode: pause the workspace for everyone except admins
        // (members who can change settings). Settings stays reachable to toggle it off.
        if ($workspaceId !== null
            && $this->preferences->bool($workspaceId, 'maintenance.enabled')
            && ! $context->can('settings.update')) {
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

        $html = $this->view->page($page, $data, 'layouts.app', [
            'user' => $this->auth->user(),
            'sidebar' => $this->sidebar->build('workspace', $context->permissions(), $features),
            'workspaceName' => $context->workspace()['name'] ?? null,
        ]);

        return Response::html($html);
    }
}
