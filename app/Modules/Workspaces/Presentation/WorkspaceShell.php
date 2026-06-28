<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Presentation;

use HaHireAI\Core\Contracts\EntitlementResolver;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Navigation\Application\SidebarBuilder;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;

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
    ) {
    }

    /** @param array<string, mixed> $data */
    public function render(WorkspaceContext $context, string $page, array $data = []): Response
    {
        $workspaceId = $context->workspaceId();
        $features = $workspaceId !== null ? $this->entitlements->gateFeatures($workspaceId) : null;

        $html = $this->view->page($page, $data, 'layouts.app', [
            'user' => $this->auth->user(),
            'sidebar' => $this->sidebar->build('workspace', $context->permissions(), $features),
            'workspaceName' => $context->workspace()['name'] ?? null,
        ]);

        return Response::html($html);
    }
}
