<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Core;

use HaHireAI\Core\Container\Container;
use HaHireAI\Core\Container\Exceptions\ContainerException;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    public function test_transient_binding_returns_new_instances(): void
    {
        $c = new Container();
        $c->bind(SampleService::class);

        $this->assertNotSame($c->make(SampleService::class), $c->make(SampleService::class));
    }

    public function test_singleton_returns_same_instance(): void
    {
        $c = new Container();
        $c->singleton(SampleService::class);

        $this->assertSame($c->make(SampleService::class), $c->make(SampleService::class));
    }

    public function test_instance_is_returned_as_is(): void
    {
        $c = new Container();
        $object = new SampleService();
        $c->instance(SampleService::class, $object);

        $this->assertSame($object, $c->make(SampleService::class));
    }

    public function test_binds_interface_to_implementation(): void
    {
        $c = new Container();
        $c->bind(SampleContract::class, SampleService::class);

        $this->assertInstanceOf(SampleService::class, $c->make(SampleContract::class));
    }

    public function test_autowires_constructor_dependencies(): void
    {
        $c = new Container();
        $consumer = $c->make(SampleConsumer::class);

        $this->assertSame('sample', $consumer->service->name());
    }

    public function test_call_resolves_callable_parameters(): void
    {
        $c = new Container();
        $result = $c->call(static fn (SampleService $s): string => $s->name());

        $this->assertSame('sample', $result);
    }

    public function test_has_reports_bindings_and_instances(): void
    {
        $c = new Container();
        $this->assertFalse($c->has(SampleService::class));
        $c->bind(SampleService::class);
        $this->assertTrue($c->has(SampleService::class));
    }

    public function test_unresolvable_scalar_throws(): void
    {
        $c = new Container();
        $this->expectException(ContainerException::class);
        $c->make(NeedsScalar::class);
    }
}

interface SampleContract
{
}

class SampleService implements SampleContract
{
    public function name(): string
    {
        return 'sample';
    }
}

class SampleConsumer
{
    public function __construct(public SampleService $service)
    {
    }
}

class NeedsScalar
{
    public function __construct(public string $value)
    {
    }
}
