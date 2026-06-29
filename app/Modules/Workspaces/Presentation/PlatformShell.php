<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workspaces\Presentation;

use HaHireAI\Core\Http\Response;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Navigation\Application\SidebarBuilder;
use HaHireAI\Modules\Workspaces\Application\PlatformContext;

/**
 * Renders a page inside the Platform Context shell (the System Owner view). Reuses
 * the same layout and the same single dynamic SidebarBuilder — only the context
 * differs ('platform'), so the sidebar shows System/Companies/Users/… instead of
 * the workspace items. No separate "admin" sidebar exists.
 */
final class PlatformShell
{
    public function __construct(
        private readonly View $view,
        private readonly SidebarBuilder $sidebar,
        private readonly AuthContext $auth,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function render(PlatformContext $context, string $page, array $data = []): Response
    {
        $html = $this->view->page($page, $data, 'layouts.app', [
            'user' => $this->auth->user(),
            'sidebar' => $this->sidebar->build('platform', $context->permissions()),
            'workspaceName' => 'Platform',
            // Red accent across the whole platform shell — distinct from the blue
            // tenant workspaces. Consumed by layouts.app → <body class="theme-platform">.
            'platformTheme' => true,
        ]);

        return Response::html($html);
    }
}
