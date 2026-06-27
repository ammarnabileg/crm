<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\App\NotificationController;
use App\Core\Model;
use App\Core\Request;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Tests\TestCase;

/**
 * Notifications web layer (docs/30) — the per-user feed renders end to end
 * (controller -> service -> view -> layout) over the existing `notifications`
 * table, and the read-side mutations work. The whole module is scoped strictly to
 * the signed-in user; the critical test below proves a notification belonging to a
 * DIFFERENT user is never returned nor affected (the security boundary).
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private int $workspaceId = 0;
    private int $userId = 0;
    private int $typeId = 0;
    private int $channelId = 0;

    public function setUp(): void
    {
        $db = app('db');
        $this->workspaceId = (int) $db->table('workspaces')->orderBy('id')->value('id');
        tenant()->setById($this->workspaceId);
        $this->userId = (int) $db->table('users')->orderBy('id')->value('id');

        // The controller resolves the feed from auth()->id(); sign the user in via the
        // session so its rendered output is scoped to this exact user (the security
        // boundary the feed enforces).
        session()->put((string) config('auth.session_key', 'auth_user_id'), $this->userId);

        // notifications.type_id (a notification_type lookup) and channel_id (the
        // in_app delivery channel) are NOT NULL — resolve the seeded ids the same way
        // the real notification pipeline would, so the fixtures are valid rows.
        $this->typeId = (int) lookup_id('notification_type', 'system');
        $this->channelId = (int) $db->table('notification_channels')->where('key', '=', 'in_app')->value('id');

        // Two notifications for the current user: one unread (read_at NULL), one read.
        $this->insertNotification('Unread notification', 'This one is unread.', null);
        $this->insertNotification('Read notification', 'This one is already read.', now());
    }

    /** Insert one notification for the signed-in user, filling the NOT-NULL columns. */
    private function insertNotification(string $title, string $body, ?string $readAt, ?int $userId = null): void
    {
        app('db')->table('notifications')->insert([
            'uuid'         => Model::generateUuid(),
            'workspace_id' => $this->workspaceId,
            'user_id'      => $userId ?? $this->userId,
            'type_id'      => $this->typeId,
            'channel_id'   => $this->channelId,
            'title'        => $title,
            'body'         => $body,
            'read_at'      => $readAt,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    /**
     * Rendering touches auth() (the AuthManager resolves the user once and caches
     * "resolved"). Reset that resolution + the AccessControl per-request cache on
     * the EXISTING singletons (preserving registered policy gates) so later
     * session-auth tests re-resolve cleanly — test isolation, no leak.
     */
    public function tearDown(): void
    {
        session()->forget((string) config('auth.session_key', 'auth_user_id'));

        $auth = app('auth');
        $r = new \ReflectionObject($auth);
        foreach (['resolved' => false, 'user' => null] as $prop => $value) {
            if ($r->hasProperty($prop)) {
                $p = $r->getProperty($prop);
                $p->setAccessible(true);
                $p->setValue($auth, $value);
            }
        }

        $access = app('access');
        $ra = new \ReflectionObject($access);
        if ($ra->hasProperty('cache')) {
            $p = $ra->getProperty('cache');
            $p->setAccessible(true);
            $p->setValue($access, []);
        }
    }

    private function request(array $query = [], array $post = []): Request
    {
        $req = new Request($query, $post, ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'], [], []);
        app()->instance('request', $req); // the layout calls request()->path()

        return $req;
    }

    public function test_index_renders_and_lists_both_notifications(): void
    {
        $res = (new NotificationController())->index($this->request());
        $this->assertSame(200, $res->getStatus());
        $content = $res->getContent();
        $this->assertTrue(str_contains($content, 'Notifications'));
        $this->assertTrue(str_contains($content, 'Unread notification'));
        $this->assertTrue(str_contains($content, 'Read notification'));
    }

    public function test_unread_count_is_one(): void
    {
        $count = (new NotificationService())->unreadCount($this->workspaceId, $this->userId);
        $this->assertSame(1, $count);
    }

    public function test_recent_returns_both_for_the_user(): void
    {
        $recent = (new NotificationService())->recent($this->workspaceId, $this->userId, 10);
        $this->assertCount(2, $recent);
    }

    public function test_mark_all_read_clears_the_unread_count(): void
    {
        $service = new NotificationService();
        $this->assertSame(1, $service->unreadCount($this->workspaceId, $this->userId));

        $service->markAllRead($this->workspaceId, $this->userId);

        $this->assertSame(0, $service->unreadCount($this->workspaceId, $this->userId));
    }

    /**
     * CRITICAL cross-user isolation: a notification owned by a DIFFERENT user must
     * never appear in this user's feed, count toward their unread, or be cleared by
     * their markAllRead. We insert one such row for a non-existent neighbouring
     * user_id and assert it is fully invisible and untouched.
     */
    public function test_notifications_for_a_different_user_are_isolated(): void
    {
        $db = app('db');
        // A REAL neighbouring user (the user_id FK requires a valid users row) whose
        // notification must never leak into the signed-in user's feed.
        $otherUserId = (int) User::create([
            'name'           => 'Other User',
            'email'          => 'other-' . uniqid() . '@notif.test',
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ])->getKey();
        $this->insertNotification('Someone else notification', 'Must never be visible to the current user.', null, $otherUserId);

        $service = new NotificationService();

        // Not returned in the current user's recent feed.
        $titles = array_map(static fn (array $n): string => (string) $n['title'], $service->recent($this->workspaceId, $this->userId, 50));
        $this->assertFalse(in_array('Someone else notification', $titles, true));

        // Not counted, and not affected by the current user's markAllRead.
        $this->assertSame(1, $service->unreadCount($this->workspaceId, $this->userId));
        $service->markAllRead($this->workspaceId, $this->userId);

        $otherStillUnread = $db->table('notifications')
            ->where('workspace_id', '=', $this->workspaceId)
            ->where('user_id', '=', $otherUserId)
            ->whereNull('read_at')
            ->count();
        $this->assertSame(1, $otherStillUnread);

        // And the controller's rendered feed never leaks the other user's title.
        $res = (new NotificationController())->index($this->request());
        $this->assertFalse(str_contains($res->getContent(), 'Someone else notification'));
    }
};
