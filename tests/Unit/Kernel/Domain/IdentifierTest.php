<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Kernel\Domain;

use DateTimeImmutable;
use Nizam\Kernel\Domain\AggregateRoot;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Kernel\Domain\Identifier;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Kernel\Domain\UserId;
use Nizam\Platform\Exception\InvalidArgumentException;
use Nizam\Platform\Support\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Identifier::class)]
#[CoversClass(TenantId::class)]
#[CoversClass(UserId::class)]
#[CoversClass(AggregateRoot::class)]
final class IdentifierTest extends TestCase
{
    public function testGenerateProducesValidUuidV7(): void
    {
        $id = TenantId::generate();

        self::assertTrue(Uuid::isV7($id->toString()));
    }

    public function testFromStringRoundTrips(): void
    {
        $uuid = Uuid::v7();
        $id = UserId::fromString($uuid);

        self::assertSame($uuid, $id->toString());
        self::assertSame($uuid, (string) $id);
    }

    public function testInvalidUuidThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TenantId::fromString('not-a-uuid');
    }

    public function testEqualityByValue(): void
    {
        $uuid = Uuid::v7();

        self::assertTrue(TenantId::fromString($uuid)->equals(TenantId::fromString($uuid)));
        self::assertFalse(TenantId::generate()->equals(TenantId::generate()));
    }

    public function testDifferentIdentifierTypesAreNeverEqual(): void
    {
        $uuid = Uuid::v7();

        $tenant = TenantId::fromString($uuid);
        $user = UserId::fromString($uuid);

        self::assertFalse($tenant->equals($user));
        self::assertFalse($user->equals($tenant));
    }

    public function testAggregateRecordsAndPullsDomainEvents(): void
    {
        $aggregate = new SampleAggregate(UserId::generate());
        $aggregate->doSomething();
        $aggregate->doSomething();

        self::assertTrue($aggregate->hasRecordedEvents());

        $events = $aggregate->pullDomainEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(DomainEvent::class, $events[0]);

        // Pulling clears the buffer.
        self::assertSame([], $aggregate->pullDomainEvents());
        self::assertFalse($aggregate->hasRecordedEvents());
    }
}

final class SampleAggregate extends AggregateRoot
{
    public function __construct(UserId $id)
    {
        parent::__construct($id);
    }

    public function doSomething(): void
    {
        $this->recordThat(new SampleDomainEvent($this->id()->toString()));
    }
}

final class SampleDomainEvent implements DomainEvent
{
    private readonly DateTimeImmutable $occurredAt;

    public function __construct(private readonly string $aggregateId)
    {
        $this->occurredAt = new DateTimeImmutable();
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function eventName(): string
    {
        return 'sample.something_happened';
    }

    public function aggregateId(): string
    {
        return $this->aggregateId;
    }
}
