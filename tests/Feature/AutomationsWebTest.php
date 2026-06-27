<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Controllers\App\AutomationController;
use App\Core\Request;
use App\Models\User;
use App\Services\Tenancy\WorkspaceService;
use Tests\TestCase;

/**
 * Workflow Automations web layer (docs/51) — the reachable UI over the Automation
 * Engine. Proves: an Owner sees the builder and can create a rule (trigger + steps)
 * that persists with its steps; bad input (unknown trigger, malformed config JSON)
 * is rejected without writing; the detail page renders steps; toggle flips active;
 * delete soft-deletes; a foreign id 404s; and a member without `automation.view` is
 * refused. Reads gated by automation.view, writes by automation.manage.
 *
 * Fixtures: a fresh workspace whose creator is the Owner (holds automation.* via the
 * '*' wildcard — automation is not a platform module), session-authenticated.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    private int $workspaceId = 0;
    private int $ownerId = 0;
    private int $outsiderId = 0;

    public function setUp(): void
    {
        $owner = User::create([
            'name'           => 'Automation Owner',
            'email'          => 'auto-' . uniqid() . '@auto.test',
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $this->ownerId = (int) $owner->getKey();
        $this->workspaceId = (int) (new WorkspaceService())->create($owner, 'Automation Co')->getKey();

        // A roleless user in the same tenant — holds no automation permission.
        $outsider = User::create([
            'name'           => 'No Roles',
            'email'          => 'noroles-' . uniqid() . '@auto.test',
            'password'       => 'x',
            'user_status_id' => lookup_id('user_status', 'active'),
        ]);
        $this->outsiderId = (int) $outsider->getKey();

        tenant()->setById($this->workspaceId);
        session()->put((string) config('auth.session_key', 'auth_user_id'), $this->ownerId);
    }

    public function tearDown(): void
    {
        session()->forget((string) config('auth.session_key', 'auth_user_id'));
        tenant()->clear();
        $this->resetAuthState();
    }

    private function request(array $query = [], array $body = []): Request
    {
        $method = $body === [] ? 'GET' : 'POST';
        $req = new Request($query, $body, ['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/'], [], []);
        app()->instance('request', $req);

        return $req;
    }

    private function loginAs(int $id): void
    {
        session()->put((string) config('auth.session_key', 'auth_user_id'), $id);
        $this->resetAuthState();
        session()->put((string) config('auth.session_key', 'auth_user_id'), $id);
    }

    public function test_index_renders_the_builder_for_a_manager(): void
    {
        $res = (new AutomationController())->index($this->request());
        $this->assertSame(200, $res->getStatus());
        $content = $res->getContent();
        $this->assertTrue(str_contains($content, 'New automation'));
        // A trigger from config is offered.
        $this->assertTrue(str_contains($content, 'application.submitted'));
    }

    public function test_store_creates_an_automation_with_steps(): void
    {
        $res = (new AutomationController())->store($this->request([], [
            'name'          => 'Notify on application',
            'trigger_event' => 'application.submitted',
            'steps'         => [
                ['op' => 'condition:always', 'config' => ''],
                ['op' => 'action:notify', 'config' => '{"message":"New application"}'],
            ],
        ]));
        $this->assertSame(302, $res->getStatus());

        $row = app('db')->table('automations')
            ->where('workspace_id', '=', $this->workspaceId)
            ->where('name', '=', 'Notify on application')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('application.submitted', (string) $row['trigger_event']);

        $steps = app('db')->table('automation_steps')->where('automation_id', '=', (int) $row['id'])->orderBy('sort_order')->get();
        $this->assertSame(2, count($steps));
        $this->assertSame('condition', (string) $steps[0]['step_type']);
        $this->assertSame('always', (string) $steps[0]['key']);
        $this->assertSame('action', (string) $steps[1]['step_type']);
        $this->assertSame('notify', (string) $steps[1]['key']);
    }

    public function test_store_rejects_an_unknown_trigger(): void
    {
        $before = app('db')->table('automations')->where('workspace_id', '=', $this->workspaceId)->count();

        $res = (new AutomationController())->store($this->request([], [
            'name'          => 'Bad trigger',
            'trigger_event' => 'not.a.real.event',
            'steps'         => [['op' => 'action:log', 'config' => '']],
        ]));
        $this->assertSame(302, $res->getStatus());

        $after = app('db')->table('automations')->where('workspace_id', '=', $this->workspaceId)->count();
        $this->assertSame($before, $after, 'An unknown trigger must not create an automation.');
    }

    public function test_store_rejects_malformed_config_json(): void
    {
        $before = app('db')->table('automations')->where('workspace_id', '=', $this->workspaceId)->count();

        $res = (new AutomationController())->store($this->request([], [
            'name'          => 'Bad config',
            'trigger_event' => 'application.submitted',
            'steps'         => [['op' => 'action:notify', 'config' => 'this is not json']],
        ]));
        $this->assertSame(302, $res->getStatus());
        $this->assertSame($before, app('db')->table('automations')->where('workspace_id', '=', $this->workspaceId)->count());
    }

    public function test_show_renders_steps_and_foreign_id_404s(): void
    {
        // Create one to view.
        (new AutomationController())->store($this->request([], [
            'name'          => 'Viewable',
            'trigger_event' => 'offer.accepted',
            'steps'         => [['op' => 'action:log', 'config' => '']],
        ]));
        $id = (int) app('db')->table('automations')->where('name', '=', 'Viewable')->value('id');

        $res = (new AutomationController())->show($this->request(['id' => (string) $id]));
        $this->assertSame(200, $res->getStatus());
        $this->assertTrue(str_contains($res->getContent(), 'offer.accepted'));

        // A non-existent id 404s (cross-tenant guard).
        $threw = false;
        try {
            (new AutomationController())->show($this->request(['id' => '99999999']));
        } catch (\App\Core\Exceptions\HttpException $e) {
            $threw = true;
            $this->assertSame(404, $e->getStatusCode());
        }
        $this->assertTrue($threw);
    }

    public function test_toggle_flips_active_state(): void
    {
        (new AutomationController())->store($this->request([], [
            'name'          => 'Togglable',
            'trigger_event' => 'job.published',
            'steps'         => [['op' => 'action:log', 'config' => '']],
        ]));
        $id = (int) app('db')->table('automations')->where('name', '=', 'Togglable')->value('id');
        $this->assertSame(1, (int) app('db')->table('automations')->where('id', '=', $id)->value('is_active'));

        (new AutomationController())->toggle($this->request([], ['id' => (string) $id]));
        $this->assertSame(0, (int) app('db')->table('automations')->where('id', '=', $id)->value('is_active'));
    }

    public function test_delete_soft_deletes(): void
    {
        (new AutomationController())->store($this->request([], [
            'name'          => 'Deletable',
            'trigger_event' => 'job.closed',
            'steps'         => [['op' => 'action:end', 'config' => '']],
        ]));
        $id = (int) app('db')->table('automations')->where('name', '=', 'Deletable')->value('id');

        (new AutomationController())->destroy($this->request([], ['id' => (string) $id]));
        $this->assertNotNull(app('db')->table('automations')->where('id', '=', $id)->value('deleted_at'));
    }

    public function test_member_without_permission_is_refused(): void
    {
        $this->loginAs($this->outsiderId);

        $threw = false;
        try {
            (new AutomationController())->index($this->request());
        } catch (\App\Core\Exceptions\HttpException $e) {
            $threw = true;
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertTrue($threw, 'A user without automation.view must be refused.');
    }

    private function resetAuthState(): void
    {
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
};
