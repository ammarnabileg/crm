<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Presentation;

use HaHireAI\Core\Contracts\NotificationFeed;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Navigation\Application\ContextSwitcher;
use HaHireAI\Modules\Navigation\Application\SidebarBuilder;
use HaHireAI\Modules\Recruitment\Application\CandidateContext;

/**
 * Renders a page inside the candidate shell — the same top bar + dynamic sidebar
 * as the staff workspace, but the menu is the candidate portal (built from the
 * 'candidate' context, not from permissions). Keeps controllers layout-free.
 */
final class CandidateShell
{
    public function __construct(
        private readonly View $view,
        private readonly SidebarBuilder $sidebar,
        private readonly AuthContext $auth,
        private readonly ContextSwitcher $switcher,
        private readonly NotificationFeed $notifications,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function render(CandidateContext $context, string $page, array $data = []): Response
    {
        $wsId = (string) $context->workspaceId();
        $uId = (string) $context->userId();
        $html = $this->view->page($page, $data, 'layouts.app', [
            'user' => $this->auth->user(),
            'sidebar' => $this->sidebar->build('candidate', []),
            'workspaceName' => $context->workspace()['name'] ?? null,
            'switcher' => $this->switcher->model('candidate'),
            'notifications' => $wsId !== '' && $uId !== '' ? $this->notifications->recentForUser($wsId, $uId) : [],
            'unreadCount' => $wsId !== '' && $uId !== '' ? $this->notifications->unreadCount($wsId, $uId) : 0,
        ]);

        return Response::html($html);
    }
}
