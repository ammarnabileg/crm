<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Runtime\Infrastructure;

use DateTimeImmutable;
use Nizam\Kernel\Domain\Clock;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Runtime\Execution\Domain\Exception\ExecutionLockException;
use Nizam\Runtime\Execution\Domain\Execution;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\ValueObject\ExecutionMetadata;
use Nizam\Runtime\Execution\Domain\ValueObject\RetryPolicy;
use Nizam\Runtime\Execution\Domain\ValueObject\TimeoutPolicy;
use Nizam\Runtime\Infrastructure\Persistence\InMemory\InMemoryExecutionEventStore;
use Nizam\Runtime\Infrastructure\Persistence\InMemory\InMemoryExecutionLockManager;
use Nizam\Runtime\Infrastructure\Persistence\InMemory\InMemoryExecutionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryExecutionRepository::class)]
#[CoversClass(InMemoryExecutionEventStore::class)]
#[CoversClass(InMemoryExecutionLockManager::class)]
final class InMemoryExecutionPersistenceTest extends TestCase
{
    private Clock $clock;

    protected function setUp(): void
    {
        $this->clock = new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-07-01T12:00:00+00:00');
            }
        };
    }

    public function testRepositoryUpsertsAndScopesByTenant(): void
    {
        $repository = new InMemoryExecutionRepository();
        $tenantA = TenantId::generate();
        $tenantB = TenantId::generate();

        $a = $this->pendingExecution($tenantA);
        $b = $this->pendingExecution($tenantB);
        $repository->save($a);
        $repository->save($b);
        $repository->save($a); // upsert, not duplicate

        self::assertNotNull($repository->ofId($a->executionId()));
        self::assertCount(1, $repository->ofTenant($tenantA));
        self::assertCount(1, $repository->ofTenant($tenantB));
        self::assertCount(0, $repository->ofTenant(TenantId::generate()));
    }

    public function testEventStoreAppendsInOrderAndStreamsBack(): void
    {
        $store = new InMemoryExecutionEventStore();
        $tenantId = TenantId::generate();
        $execution = $this->pendingExecution($tenantId);
        $execution->plan($this->clock);

        $events = $execution->pullDomainEvents();
        $store->append($execution->executionId(), $events);

        $streamed = $store->stream($execution->executionId());
        self::assertCount(count($events), $streamed);
        self::assertSame('runtime.execution_started', $streamed[0]->eventName());
    }

    public function testEmptyAppendIsANoOp(): void
    {
        $store = new InMemoryExecutionEventStore();
        $id = ExecutionId::generate();
        $store->append($id, []);

        self::assertSame([], $store->stream($id));
    }

    public function testLockManagerSerializesAndReleases(): void
    {
        $locks = new InMemoryExecutionLockManager($this->clock);

        $lock = $locks->acquire('exec-1', 500);
        self::assertNotNull($lock);

        // Held: a second acquire on a live key is refused.
        self::assertNull($locks->acquire('exec-1', 500));

        $locks->release($lock);
        self::assertNotNull($locks->acquire('exec-1', 500));
    }

    public function testReleasingAnUnownedLockRaises(): void
    {
        $locks = new InMemoryExecutionLockManager($this->clock);
        $lock = $locks->acquire('exec-1', 500);
        self::assertNotNull($lock);

        $other = new InMemoryExecutionLockManager($this->clock);
        // A lock from a different manager's key space is not owned here.
        $foreign = $other->acquire('exec-2', 500);
        self::assertNotNull($foreign);

        $this->expectException(ExecutionLockException::class);
        $locks->release($foreign);
    }

    private function pendingExecution(TenantId $tenantId): Execution
    {
        return Execution::start(
            ExecutionId::generate(),
            new ExecutionMetadata($tenantId, null, null, null, 'crm.enrich'),
            RetryPolicy::default(),
            TimeoutPolicy::default(),
            $this->clock,
        );
    }
}
