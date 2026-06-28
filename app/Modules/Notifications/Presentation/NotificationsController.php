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

    public function index(Request $request): Response
    {
        if (($r = $this->gate('notification.view')) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $userId = (string) $this->context->userId();

        $state = (string) $request->input('state', 'all');
        if (! in_array($state, ['all', 'unread', 'archived'], true)) {
            $state = 'all';
        }
        $filters = [
            'state' => $state,
            'category' => trim((string) $request->input('category', '')),
            'q' => trim((string) $request->input('q', '')),
        ];

        return $this->shell->render($this->context, 'notifications.index', [
            'notifications' => $this->notifications->forUser($ws, $userId, $filters),
            'unread' => $this->notifications->unreadCount($ws, $userId),
            'counts' => $this->notifications->counts($ws, $userId),
            'categories' => $this->notifications->categories($ws, $userId),
            'filters' => $filters,
            'status' => $this->session->pullFlash('status'),
        ]);
    }

    public function archive(Request $request, string $id): Response
    {
        if (($r = $this->gate('notification.view', $request)) !== null) {
            return $r;
        }

        $this->notifications->archive((string) $this->context->workspaceId(), (string) $this->context->userId(), $id);
        $this->session->flash('status', 'Notification archived.');

        return Response::redirect($this->backTo($request));
    }

    public function unarchive(Request $request, string $id): Response
    {
        if (($r = $this->gate('notification.view', $request)) !== null) {
            return $r;
        }

        $this->notifications->unarchive((string) $this->context->workspaceId(), (string) $this->context->userId(), $id);
        $this->session->flash('status', 'Notification restored.');

        return Response::redirect($this->backTo($request));
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

    /** Same-origin relative redirect back to the notifications view the action came from. */
    private function backTo(Request $request): string
    {
        $to = (string) $request->input('return_to', '/notifications');

        return (str_starts_with($to, '/notifications') && ! str_contains($to, '://')) ? $to : '/notifications';
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
