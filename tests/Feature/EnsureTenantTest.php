<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Request;
use App\Core\Response;
use App\Http\Middleware\EnsureTenant;
use Tests\TestCase;

/**
 * EnsureTenant guard (production 500 regression). Tenant-scoped pages (AI Settings,
 * Workspace Settings, Billing, Members, …) read tenant-scoped Models/Settings that
 * THROW without an active tenant. A user with no active workspace — including a fresh
 * super admin straight after install — must be redirected to the workspace chooser,
 * never let through to a page that then 500s. (Platform `/system/*` ops are gated by
 * permission and are not behind this middleware, so super admins keep access.)
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function tearDown(): void
    {
        tenant()->clear();
    }

    private function request(): Request
    {
        $req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/ai'], [], []);
        app()->instance('request', $req);

        return $req;
    }

    public function test_passes_through_when_a_tenant_is_active(): void
    {
        $workspaceId = (int) app('db')->table('workspaces')->orderBy('id')->value('id');
        tenant()->setById($workspaceId);

        $res = (new EnsureTenant())->handle($this->request(), static fn (): Response => Response::make('passed', 200));

        $this->assertSame(200, $res->getStatus());
        $this->assertSame('passed', $res->getContent());
    }

    public function test_redirects_to_workspace_select_without_an_active_tenant(): void
    {
        tenant()->clear();

        $reached = false;
        $res = (new EnsureTenant())->handle($this->request(), static function () use (&$reached): Response {
            $reached = true;

            return Response::make('should-not-run', 200);
        });

        // The tenant-scoped page is never reached; the user is redirected instead.
        $this->assertFalse($reached);
        $this->assertSame(302, $res->getStatus());
        $this->assertTrue(str_contains((string) $res->getHeader('Location'), 'workspaces/select'));
    }
};
