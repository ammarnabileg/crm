<?php

declare(strict_types=1);

namespace App\Controllers\App;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Notifications\NotificationService;

/**
 * Notifications (docs/30) — the signed-in user's own notification feed: full
 * paginated list plus mark-one / mark-all read. Any authenticated tenant user may
 * view their own notifications, so no special permission gates these routes; the
 * security boundary is the per-user scope, re-derived from auth()/tenant() in
 * EVERY action (never from request input).
 */
final class NotificationController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Request $request): Response
    {
        $db = app('db');
        $workspaceId = (int) tenant()->id();
        $userId = (int) auth()->id();

        $page = max(1, (int) $request->input('page', 1));

        $scoped = static fn () => $db->table('notifications')
            ->where('workspace_id', '=', $workspaceId)
            ->where('user_id', '=', $userId);

        $total = $scoped()->count();
        $last = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $last);

        $notifications = $scoped()
            ->orderBy('created_at', 'desc')
            ->limit(self::PER_PAGE)
            ->offset(($page - 1) * self::PER_PAGE)
            ->get();

        return $this->view('app.notifications.index', [
            'title'         => 'Notifications',
            'notifications' => $notifications,
            'unreadCount'   => (new NotificationService())->unreadCount($workspaceId, $userId),
            'page'          => $page,
            'lastPage'      => $last,
        ]);
    }

    public function markRead(Request $request): Response
    {
        $id = (int) $request->input('id', 0);
        abort_unless($id > 0, 404);

        // Re-scope from the authenticated session — request input only carries the id.
        (new NotificationService())->markRead($id, (int) tenant()->id(), (int) auth()->id());

        $this->withSuccess('Notification marked as read.');

        return $this->back();
    }

    public function markAllRead(Request $request): Response
    {
        (new NotificationService())->markAllRead((int) tenant()->id(), (int) auth()->id());

        $this->withSuccess('All notifications marked as read.');

        return $this->back();
    }
}
