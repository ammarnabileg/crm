<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Platform\Container;

use Nizam\Platform\Container\Container;
use Nizam\Platform\Container\ContainerException;
use Nizam\Platform\Container\NotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(Container::class)]
#[CoversClass(ContainerException::class)]
#[CoversClass(NotFoundException::class)]
final class ContainerTest extends TestCase
{
    public function testImplementsPsr11(): void
    {
        self::assertInstanceOf(ContainerInterface::class, new Container());
    }

    public function testBindProducesFreshInstances(): void
    {
        $container = new Container();
        $container->bind(ContainerFixtureLeaf::class);

        $a = $container->get(ContainerFixtureLeaf::class);
        $b = $container->get(ContainerFixtureLeaf::class);

        self::assertInstanceOf(ContainerFixtureLeaf::class, $a);
        self::assertNotSame($a, $b);
    }

    public function testSingletonReturnsSameInstance(): void
    {
        $container = new Container();
        $container->singleton(ContainerFixtureLeaf::class);

        self::assertSame(
            $container->get(ContainerFixtureLeaf::class),
            $container->get(ContainerFixtureLeaf::class),
        );
    }

    public function testInstanceIsReturnedAsIs(): void
    {
        $container = new Container();
        $leaf = new ContainerFixtureLeaf();
        $container->instance('leaf', $leaf);

        self::assertSame($leaf, $container->get('leaf'));
        self::assertTrue($container->has('leaf'));
    }

    public function testClosureBindingReceivesContainer(): void
    {
        $container = new Container();
        $container->bind('answer', static fn (Container $c): int => 42);

        self::assertSame(42, $container->get('answer'));
    }

    public function testAutowiresNestedConstructorDependencies(): void
    {
        $container = new Container();

        $root = $container->get(ContainerFixtureRoot::class);

        self::assertInstanceOf(ContainerFixtureRoot::class, $root);
        self::assertInstanceOf(ContainerFixtureMiddle::class, $root->middle);
        self::assertInstanceOf(ContainerFixtureLeaf::class, $root->middle->leaf);
    }

    public function testAutowiringResolvesInterfaceToBoundImplementation(): void
    {
        $container = new Container();
        $container->bind(ContainerFixtureInterface::class, ContainerFixtureImpl::class);

        $consumer = $container->get(ContainerFixtureConsumer::class);

        self::assertInstanceOf(ContainerFixtureImpl::class, $consumer->dependency);
    }

    public function testMakeAppliesParameterOverrides(): void
    {
        $container = new Container();

        $obj = $container->make(ContainerFixtureScalar::class, ['name' => 'override']);

        self::assertSame('override', $obj->name);
    }

    public function testCallInjectsDependenciesIntoClosure(): void
    {
        $container = new Container();

        $result = $container->call(static fn (ContainerFixtureLeaf $leaf): string => $leaf::class);

        self::assertSame(ContainerFixtureLeaf::class, $result);
    }

    public function testCallInvokesMethodArray(): void
    {
        $container = new Container();
        $target = new ContainerFixtureInvokable();

        self::assertSame('handled:x', $container->call([$target, 'handle'], ['value' => 'x']));
    }

    public function testGetUnknownStringThrowsNotFound(): void
    {
        $this->expectException(NotFoundException::class);

        (new Container())->get('nonexistent.service.id');
    }

    public function testUnresolvableScalarThrowsContainerException(): void
    {
        $this->expectException(ContainerException::class);

        (new Container())->get(ContainerFixtureScalar::class);
    }

    public function testCircularDependencyThrows(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/Circular dependency/');

        (new Container())->get(ContainerFixtureCycleA::class);
    }
}

final class ContainerFixtureLeaf
{
}

final class ContainerFixtureMiddle
{
    public function __construct(public readonly ContainerFixtureLeaf $leaf)
    {
    }
}

final class ContainerFixtureRoot
{
    public function __construct(public readonly ContainerFixtureMiddle $middle)
    {
    }
}

interface ContainerFixtureInterface
{
}

final class ContainerFixtureImpl implements ContainerFixtureInterface
{
}

final class ContainerFixtureConsumer
{
    public function __construct(public readonly ContainerFixtureInterface $dependency)
    {
    }
}

final class ContainerFixtureScalar
{
    public function __construct(public readonly string $name)
    {
    }
}

final class ContainerFixtureInvokable
{
    public function handle(string $value): string
    {
        return 'handled:' . $value;
    }
}

final class ContainerFixtureCycleA
{
    public function __construct(public readonly ContainerFixtureCycleB $b)
    {
    }
}

final class ContainerFixtureCycleB
{
    public function __construct(public readonly ContainerFixtureCycleA $a)
    {
    }
}
