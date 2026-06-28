<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Notifications\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Notifications\Application\NotificationService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/** The personal Notification Center for the current (workspace, user). */
final class NotificationsController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly NotificationService $notifications,
        private readonly Session $session,
    ) {
    }

    public function index(): Response
    {
        if (($r = $this->gate('notification.view')) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $userId = (string) $this->context->userId();

        return $this->shell->render($this->context, 'notifications.index', [
            'notifications' => $this->notifications->forUser($ws, $userId),
            'unread' => $this->notifications->unreadCount($ws, $userId),
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function markAll(Request $request): Response
    {
        if (($r = $this->gate('notification.view', $request)) !== null) {
            return $r;
        }

        $this->notifications->markAllRead((string) $this->context->workspaceId(), (string) $this->context->userId());
        $this->session->flash('status', 'All notifications marked as read.');

        return Response::redirect('/notifications');
    }

    public function read(Request $request, string $id): Response
    {
        if (($r = $this->gate('notification.view', $request)) !== null) {
            return $r;
        }

        $this->notifications->markRead((string) $this->context->workspaceId(), (string) $this->context->userId(), $id);

        return Response::redirect('/notifications');
    }

    private function gate(string $permission, ?Request $request = null): ?Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (! $this->context->can($permission)) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }
        if ($request !== null && ! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>419</h1><p>Security check failed.</p>', 419);
        }

        return null;
    }
}
