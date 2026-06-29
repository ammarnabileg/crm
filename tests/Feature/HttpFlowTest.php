<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Container\Container;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Kernel;
use PHPUnit\Framework\TestCase;

/**
 * HTTP-level gating through the real kernel + modules (no DB required for these
 * redirect checks). The full install→login→workspace flow is verified e2e
 * separately; the foundation logic is covered by FoundationAcceptanceTest.
 */
final class HttpFlowTest extends TestCase
{
    protected function tearDown(): void
    {
        restore_error_handler();
        restore_exception_handler();
        parent::tearDown();
    }

    private function kernel(): Kernel
    {
        return (new Kernel(new Container(), dirname(__DIR__, 2)))->boot();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->kernel()->handle(new Request('GET', '/dashboard'));

        $this->assertSame(302, $response->status());
        $this->assertSame('/login', $response->headers()['Location'] ?? null);
    }

    public function test_create_workspace_requires_authentication(): void
    {
        $response = $this->kernel()->handle(new Request('GET', '/workspaces/create'));

        $this->assertSame(302, $response->status());
        $this->assertSame('/login', $response->headers()['Location'] ?? null);
    }

    public function test_logout_redirects_to_login(): void
    {
        $response = $this->kernel()->handle(new Request('POST', '/logout'));

        $this->assertSame(302, $response->status());
        $this->assertSame('/login', $response->headers()['Location'] ?? null);
    }

    public function test_install_and_login_routes_are_registered(): void
    {
        // Both routes resolve to a response (200 render or 302 gate) — never 404.
        $kernel = $this->kernel();
        $install = $kernel->handle(new Request('GET', '/install'));
        $login = $kernel->handle(new Request('GET', '/login'));

        $this->assertNotSame(404, $install->status());
        $this->assertNotSame(404, $login->status());
    }
}
