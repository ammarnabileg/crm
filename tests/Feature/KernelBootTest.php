<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Feature;

use HaHireAI\Core\Container\Container;
use HaHireAI\Core\Contracts\Container as ContainerContract;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Kernel;
use HaHireAI\Core\Modules\ModuleRegistry;
use HaHireAI\Core\Routing\Router;
use PHPUnit\Framework\TestCase;

/**
 * Phase 7 acceptance: the kernel boots and serves requests with NO modules.
 */
final class KernelBootTest extends TestCase
{
    protected function tearDown(): void
    {
        // The kernel registers global error/exception handlers on boot; restore
        // them so PHPUnit does not flag handler leakage between tests.
        restore_error_handler();
        restore_exception_handler();
        parent::tearDown();
    }

    private function kernel(): Kernel
    {
        return (new Kernel(new Container(), dirname(__DIR__, 2)))->boot();
    }

    public function test_kernel_boots_and_loads_configured_modules(): void
    {
        $kernel = $this->kernel();

        $this->assertTrue($kernel->isBooted());
        // Foundation modules are registered explicitly via config/modules.php.
        $this->assertGreaterThanOrEqual(3, $kernel->container()->make(ModuleRegistry::class)->count());
    }

    public function test_container_resolves_core_services(): void
    {
        $c = $this->kernel()->container();

        $this->assertInstanceOf(ContainerContract::class, $c->make(ContainerContract::class));
        $this->assertSame($c->make(Router::class), $c->make(Router::class));
    }

    public function test_root_route_redirects_based_on_state(): void
    {
        $response = $this->kernel()->handle(new Request('GET', '/'));

        // Root routes the visitor to install/login/dashboard depending on state.
        $this->assertSame(302, $response->status());
        $this->assertArrayHasKey('Location', $response->headers());
    }

    public function test_liveness_route(): void
    {
        $response = $this->kernel()->handle(new Request('GET', '/up'));

        $this->assertSame(200, $response->status());
        $this->assertSame('OK', $response->content());
    }

    public function test_unknown_route_is_404(): void
    {
        $this->assertSame(404, $this->kernel()->handle(new Request('GET', '/__missing__'))->status());
    }

    public function test_health_endpoint_is_healthy(): void
    {
        $response = $this->kernel()->handle(new Request('GET', '/health'));

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('"status":"healthy"', $response->content());
    }
}
