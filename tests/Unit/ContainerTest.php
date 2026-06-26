<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Container;
use RuntimeException;
use Tests\TestCase;

interface GreeterContract
{
    public function hi(): string;
}

final class EnglishGreeter implements GreeterContract
{
    public function hi(): string
    {
        return 'hello';
    }
}

final class Welcomer
{
    public function __construct(public GreeterContract $greeter)
    {
    }
}

/**
 * Verifies the container's binding, interface→concrete resolution, reflection
 * autowiring, singletons, and failure behavior (docs/47 EAS-2).
 */
return new class extends TestCase {
    public function test_binds_interface_to_concrete_class(): void
    {
        $c = new Container();
        $c->bind(GreeterContract::class, EnglishGreeter::class);

        $this->assertInstanceOf(EnglishGreeter::class, $c->make(GreeterContract::class));
    }

    public function test_binds_interface_to_closure(): void
    {
        $c = new Container();
        $c->bind(GreeterContract::class, fn () => new EnglishGreeter());

        $this->assertSame('hello', $c->make(GreeterContract::class)->hi());
    }

    public function test_autowires_constructor_dependencies(): void
    {
        $c = new Container();
        $c->bind(GreeterContract::class, EnglishGreeter::class);

        $welcomer = $c->make(Welcomer::class);

        $this->assertInstanceOf(Welcomer::class, $welcomer);
        $this->assertSame('hello', $welcomer->greeter->hi());
    }

    public function test_singleton_returns_same_instance(): void
    {
        $c = new Container();
        $c->singleton(GreeterContract::class, EnglishGreeter::class);

        $this->assertTrue($c->make(GreeterContract::class) === $c->make(GreeterContract::class));
    }

    public function test_non_singleton_returns_fresh_instances(): void
    {
        $c = new Container();
        $c->bind(GreeterContract::class, EnglishGreeter::class);

        $this->assertFalse($c->make(GreeterContract::class) === $c->make(GreeterContract::class));
    }

    public function test_instance_is_returned_as_is(): void
    {
        $c = new Container();
        $greeter = new EnglishGreeter();
        $c->instance(GreeterContract::class, $greeter);

        $this->assertTrue($c->make(GreeterContract::class) === $greeter);
    }

    public function test_unresolvable_abstract_throws(): void
    {
        $c = new Container();
        $this->assertThrows(
            static fn () => $c->make('App\\Does\\Not\\Exist'),
            RuntimeException::class
        );
    }

    public function test_unbound_interface_throws(): void
    {
        $c = new Container();
        // Welcomer needs GreeterContract; without a binding it cannot autowire.
        $this->assertThrows(
            static fn () => $c->make(Welcomer::class),
            RuntimeException::class
        );
    }
};
