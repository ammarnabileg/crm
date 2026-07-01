<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Kernel\Application;

use Nizam\Kernel\Application\Command;
use Nizam\Kernel\Application\Query;
use Nizam\Kernel\Application\SimpleCommandBus;
use Nizam\Kernel\Application\SimpleQueryBus;
use Nizam\Platform\Exception\PlatformException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SimpleCommandBus::class)]
#[CoversClass(SimpleQueryBus::class)]
final class SimpleCommandBusTest extends TestCase
{
    public function testDispatchesToRegisteredHandlerAndReturnsResult(): void
    {
        $bus = new SimpleCommandBus();
        $bus->register(GreetCommand::class, static fn (GreetCommand $c): string => 'Hello, ' . $c->name);

        self::assertSame('Hello, Ada', $bus->dispatch(new GreetCommand('Ada')));
        self::assertTrue($bus->hasHandlerFor(GreetCommand::class));
    }

    public function testDuplicateRegistrationThrows(): void
    {
        $bus = new SimpleCommandBus();
        $bus->register(GreetCommand::class, static fn (GreetCommand $c): string => 'a');

        $this->expectException(PlatformException::class);
        $bus->register(GreetCommand::class, static fn (GreetCommand $c): string => 'b');
    }

    public function testDispatchingUnregisteredCommandThrows(): void
    {
        $this->expectException(PlatformException::class);
        $this->expectExceptionMessageMatches('/No command handler registered/');

        (new SimpleCommandBus())->dispatch(new GreetCommand('X'));
    }

    public function testQueryBusMirrorsCommandBus(): void
    {
        $bus = new SimpleQueryBus();
        $bus->register(SumQuery::class, static fn (SumQuery $q): int => $q->a + $q->b);

        self::assertSame(5, $bus->dispatch(new SumQuery(2, 3)));
        self::assertFalse($bus->hasHandlerFor(GreetCommand::class));
    }

    public function testQueryBusRejectsUnregisteredQuery(): void
    {
        $this->expectException(PlatformException::class);

        (new SimpleQueryBus())->dispatch(new SumQuery(1, 1));
    }
}

final class GreetCommand implements Command
{
    public function __construct(public readonly string $name)
    {
    }
}

final class SumQuery implements Query
{
    public function __construct(public readonly int $a, public readonly int $b)
    {
    }
}
