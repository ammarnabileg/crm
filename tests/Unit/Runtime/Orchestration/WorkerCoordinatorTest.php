<?php

declare(strict_types=1);

namespace Nizam\Tests\Unit\Runtime\Orchestration;

use DateTimeImmutable;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Plugin\PermissionSet;
use Nizam\Platform\Plugin\PluginPermission;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Orchestration\Exception\OrchestrationException;
use Nizam\Runtime\Orchestration\Testing\DeterministicWorkerInvoker;
use Nizam\Runtime\Orchestration\Testing\FakeWorkerPlugin;
use Nizam\Runtime\Orchestration\ValueObject\DispatchMode;
use Nizam\Runtime\Orchestration\ValueObject\ExecutionContext;
use Nizam\Runtime\Orchestration\ValueObject\WorkerAssignment;
use Nizam\Runtime\Orchestration\ValueObject\WorkTask;
use Nizam\Runtime\Orchestration\WorkerCoordinator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkerCoordinator::class)]
final class WorkerCoordinatorTest extends TestCase
{
    private function context(): ExecutionContext
    {
        return new ExecutionContext(
            executionId: ExecutionId::generate(),
            tenantId: TenantId::generate(),
            userId: null,
            grantedPermissions: PermissionSet::of([
                new PluginPermission(FakeWorkerPlugin::REQUIRED_PERMISSION, 'granted'),
            ]),
            correlationId: 'corr-1',
            deadline: new DateTimeImmutable('2999-01-01T00:00:00+00:00'),
        );
    }

    private function assignment(string $taskId): WorkerAssignment
    {
        return new WorkerAssignment(
            new FakeWorkerPlugin(),
            new WorkTask($taskId, 'runtime.echo', 'runtime.fake-worker', ['id' => $taskId], 'do ' . $taskId),
        );
    }

    public function testSequentialDispatchReturnsOneResultPerAssignmentInOrder(): void
    {
        $coordinator = new WorkerCoordinator(new DeterministicWorkerInvoker());

        $results = $coordinator->dispatch(
            [$this->assignment('a'), $this->assignment('b')],
            DispatchMode::Sequential,
            $this->context(),
        );

        self::assertCount(2, $results);
        self::assertSame(['id' => 'a'], $results[0]->taskResult());
        self::assertSame(['id' => 'b'], $results[1]->taskResult());
    }

    public function testParallelFanOutIsDeterministicAndOrdered(): void
    {
        $coordinator = new WorkerCoordinator(new DeterministicWorkerInvoker());

        $assignments = [$this->assignment('a'), $this->assignment('b'), $this->assignment('c')];
        $first = $coordinator->dispatch($assignments, DispatchMode::Parallel, $this->context());
        $second = $coordinator->dispatch($assignments, DispatchMode::Parallel, $this->context());

        self::assertCount(3, $first);
        self::assertSame('a', $first[0]->taskResult()['id']);
        self::assertSame('c', $first[2]->taskResult()['id']);
        // Deterministic: same inputs, same ordered outputs.
        self::assertSame(
            array_map(static fn ($r) => $r->taskResult()['id'], $first),
            array_map(static fn ($r) => $r->taskResult()['id'], $second),
        );
    }

    public function testCollaborativeReEntryRunsManagerSuppliedFollowUps(): void
    {
        $coordinator = new WorkerCoordinator(new DeterministicWorkerInvoker());
        $rounds = 0;

        $results = $coordinator->dispatchCollaborative(
            [$this->assignment('a')],
            $this->context(),
            function (array $resultsSoFar) use (&$rounds): array {
                // The manager (not a worker) decides more help is needed once, by re-entering.
                if ($rounds === 0) {
                    ++$rounds;

                    return [$this->assignment('b')];
                }

                return [];
            },
        );

        self::assertSame(1, $rounds);
        self::assertCount(2, $results);
        self::assertSame('a', $results[0]->taskResult()['id']);
        self::assertSame('b', $results[1]->taskResult()['id']);
    }

    public function testCollaborationIsBoundedByTheRoundCap(): void
    {
        $coordinator = new WorkerCoordinator(new DeterministicWorkerInvoker());
        $calls = 0;

        // A resolver that always asks for more would loop forever without the cap.
        $results = $coordinator->dispatchCollaborative(
            [$this->assignment('seed')],
            $this->context(),
            function () use (&$calls): array {
                ++$calls;

                return [$this->assignment('more-' . $calls)];
            },
        );

        self::assertSame(WorkerCoordinator::MAX_COLLABORATION_ROUNDS, $calls);
        self::assertCount(1 + WorkerCoordinator::MAX_COLLABORATION_ROUNDS, $results);
    }

    public function testDispatchRequiresAtLeastOneAssignment(): void
    {
        $coordinator = new WorkerCoordinator(new DeterministicWorkerInvoker());

        $this->expectException(\Nizam\Platform\Exception\InvalidArgumentException::class);

        $coordinator->dispatch([], DispatchMode::Sequential, $this->context());
    }

    public function testWorkerToWorkerCallIsStructurallyImpossible(): void
    {
        // A worker is only ever handed its WorkTask and the read-only ExecutionContext via the invoker;
        // it never receives the coordinator or another worker. The invoker's signature is the proof:
        // there is no parameter through which a worker could reach a peer or re-enter dispatch.
        $invoke = new \ReflectionMethod(\Nizam\Runtime\Orchestration\Port\WorkerInvoker::class, 'invoke');
        $types = array_map(
            static fn (\ReflectionParameter $p): string => (string) $p->getType(),
            $invoke->getParameters(),
        );

        self::assertNotContains(WorkerCoordinator::class, $types);
        self::assertNotContains(WorkerAssignment::class, $types);
    }

    public function testDispatchIsDeniedWhenPermissionsAreNotGranted(): void
    {
        $coordinator = new WorkerCoordinator(new DeterministicWorkerInvoker());

        $ungranted = new ExecutionContext(
            executionId: ExecutionId::generate(),
            tenantId: TenantId::generate(),
            userId: null,
            grantedPermissions: PermissionSet::empty(),
            correlationId: 'corr-2',
            deadline: new DateTimeImmutable('2999-01-01T00:00:00+00:00'),
        );

        $this->expectException(OrchestrationException::class);

        $coordinator->dispatch([$this->assignment('a')], DispatchMode::Sequential, $ungranted);
    }
}
