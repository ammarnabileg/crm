<?php

declare(strict_types=1);

namespace Nizam\Runtime\Infrastructure\Persistence\Pdo;

use DateTimeImmutable;
use Nizam\Kernel\Domain\DomainEvent;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Kernel\Domain\UserId;
use Nizam\Platform\Exception\PlatformException;
use Nizam\Platform\Support\Json;
use Nizam\Runtime\Execution\Domain\Event\ExecutionApproved;
use Nizam\Runtime\Execution\Domain\Event\ExecutionCancelled;
use Nizam\Runtime\Execution\Domain\Event\ExecutionCompleted;
use Nizam\Runtime\Execution\Domain\Event\ExecutionFailed;
use Nizam\Runtime\Execution\Domain\Event\ExecutionPlanned;
use Nizam\Runtime\Execution\Domain\Event\ExecutionRecovered;
use Nizam\Runtime\Execution\Domain\Event\ExecutionRejected;
use Nizam\Runtime\Execution\Domain\Event\ExecutionRetried;
use Nizam\Runtime\Execution\Domain\Event\ExecutionSentToReview;
use Nizam\Runtime\Execution\Domain\Event\ExecutionStarted;
use Nizam\Runtime\Execution\Domain\Event\ExecutionStepCompleted;
use Nizam\Runtime\Execution\Domain\Event\ExecutionStepStarted;
use Nizam\Runtime\Execution\Domain\Event\WorkTaskAssigned;
use Nizam\Runtime\Execution\Domain\ExecutionId;
use Nizam\Runtime\Execution\Domain\ExecutionStepId;
use Nizam\Runtime\Execution\Domain\ValueObject\BackoffStrategy;
use Nizam\Runtime\Execution\Domain\ValueObject\EvidenceItem;
use Nizam\Runtime\Execution\Domain\ValueObject\ExecutionMetadata;
use Nizam\Runtime\Execution\Domain\ValueObject\RetryPolicy;
use Nizam\Runtime\Execution\Domain\ValueObject\TimeoutPolicy;
use Nizam\Runtime\Execution\Domain\ValueObject\WorkerResult;

/**
 * The append-only event-store hydrator: it serializes an execution {@see DomainEvent} to a scalar,
 * JSON-safe payload and rebuilds the exact event back from a stored row.
 *
 * Event-sourced reconstitution ({@see \Nizam\Runtime\Execution\Domain\Execution::replay()}) requires
 * every recorded event to survive a round trip through the database bit-for-bit — including the rich
 * value objects some events carry ({@see ExecutionMetadata}, {@see RetryPolicy}, {@see TimeoutPolicy},
 * {@see WorkerResult}, {@see EvidenceItem}). This serializer owns that mapping in one place: it derives a
 * stable event name (the event's own {@see DomainEvent::eventName()}) and a payload array from each
 * concrete event, and reverses the mapping on read. It performs no I/O; the
 * {@see PdoExecutionEventStore} calls it to translate rows. An unknown event name on read raises a
 * {@see PlatformException} rather than silently dropping history.
 */
final class ExecutionEventSerializer
{
    /**
     * Serialize an event into its stable name and JSON-safe payload.
     *
     * @return array{name: string, payload: array<string, mixed>}
     */
    public function serialize(DomainEvent $event): array
    {
        return [
            'name' => $event->eventName(),
            'payload' => $this->payloadOf($event),
        ];
    }

    /**
     * Encode an event's payload to a JSON string for a JSONB/TEXT column.
     */
    public function encode(DomainEvent $event): string
    {
        return Json::encode($this->payloadOf($event));
    }

    /**
     * Rebuild the concrete event from its stored name, JSON payload, and occurrence instant.
     *
     * @throws PlatformException When the event name is not recognised.
     */
    public function deserialize(string $name, string $payloadJson, DateTimeImmutable $occurredAt): DomainEvent
    {
        $payload = Json::decode($payloadJson);
        $executionId = ExecutionId::fromString($this->string($payload, 'executionId'));

        return match ($name) {
            'runtime.execution_started' => new ExecutionStarted(
                $executionId,
                $this->metadataFrom($this->array($payload, 'metadata')),
                $this->retryPolicyFrom($this->array($payload, 'retryPolicy')),
                $this->timeoutPolicyFrom($this->array($payload, 'timeoutPolicy')),
                $occurredAt,
            ),
            'runtime.execution_planned' => new ExecutionPlanned($executionId, $occurredAt),
            'runtime.work_task_assigned' => new WorkTaskAssigned(
                $executionId,
                $this->stepsFrom($this->array($payload, 'steps')),
                $occurredAt,
            ),
            'runtime.execution_step_started' => new ExecutionStepStarted(
                $executionId,
                ExecutionStepId::fromString($this->string($payload, 'stepId')),
                $occurredAt,
            ),
            'runtime.execution_step_completed' => new ExecutionStepCompleted(
                $executionId,
                ExecutionStepId::fromString($this->string($payload, 'stepId')),
                $this->workerResultFrom($this->array($payload, 'result')),
                $occurredAt,
            ),
            'runtime.execution_retried' => new ExecutionRetried(
                $executionId,
                $this->int($payload, 'attempt'),
                $this->string($payload, 'reason'),
                $occurredAt,
            ),
            'runtime.execution_sent_to_review' => new ExecutionSentToReview($executionId, $occurredAt),
            'runtime.execution_approved' => new ExecutionApproved(
                $executionId,
                $this->string($payload, 'approvedBy'),
                $occurredAt,
            ),
            'runtime.execution_rejected' => new ExecutionRejected(
                $executionId,
                $this->string($payload, 'rejectedBy'),
                $this->string($payload, 'reason'),
                $occurredAt,
            ),
            'runtime.execution_completed' => new ExecutionCompleted($executionId, $occurredAt),
            'runtime.execution_failed' => new ExecutionFailed(
                $executionId,
                $this->string($payload, 'reason'),
                $occurredAt,
            ),
            'runtime.execution_recovered' => new ExecutionRecovered($executionId, $occurredAt),
            'runtime.execution_cancelled' => new ExecutionCancelled(
                $executionId,
                $this->string($payload, 'cancelledBy'),
                $occurredAt,
            ),
            default => throw new PlatformException(
                sprintf('Cannot deserialize unknown execution event "%s".', $name),
            ),
        };
    }

    /**
     * Derive the scalar payload for a concrete event.
     *
     * @return array<string, mixed>
     *
     * @throws PlatformException When the event type is not one the Runtime records.
     */
    private function payloadOf(DomainEvent $event): array
    {
        return match (true) {
            $event instanceof ExecutionStarted => [
                'executionId' => $event->executionId()->toString(),
                'metadata' => $event->metadata()->toArray(),
                'retryPolicy' => $event->retryPolicy()->toArray(),
                'timeoutPolicy' => $event->timeoutPolicy()->toArray(),
            ],
            $event instanceof ExecutionPlanned => [
                'executionId' => $event->executionId()->toString(),
            ],
            $event instanceof WorkTaskAssigned => [
                'executionId' => $event->executionId()->toString(),
                'steps' => $event->steps(),
            ],
            $event instanceof ExecutionStepStarted => [
                'executionId' => $event->executionId()->toString(),
                'stepId' => $event->stepId()->toString(),
            ],
            $event instanceof ExecutionStepCompleted => [
                'executionId' => $event->executionId()->toString(),
                'stepId' => $event->stepId()->toString(),
                'result' => $event->result()->toArray(),
            ],
            $event instanceof ExecutionRetried => [
                'executionId' => $event->executionId()->toString(),
                'attempt' => $event->attempt(),
                'reason' => $event->reason(),
            ],
            $event instanceof ExecutionSentToReview => [
                'executionId' => $event->executionId()->toString(),
            ],
            $event instanceof ExecutionApproved => [
                'executionId' => $event->executionId()->toString(),
                'approvedBy' => $event->approvedBy(),
            ],
            $event instanceof ExecutionRejected => [
                'executionId' => $event->executionId()->toString(),
                'rejectedBy' => $event->rejectedBy(),
                'reason' => $event->reason(),
            ],
            $event instanceof ExecutionCompleted => [
                'executionId' => $event->executionId()->toString(),
            ],
            $event instanceof ExecutionFailed => [
                'executionId' => $event->executionId()->toString(),
                'reason' => $event->reason(),
            ],
            $event instanceof ExecutionRecovered => [
                'executionId' => $event->executionId()->toString(),
            ],
            $event instanceof ExecutionCancelled => [
                'executionId' => $event->executionId()->toString(),
                'cancelledBy' => $event->cancelledBy(),
            ],
            default => throw new PlatformException(
                sprintf('Cannot serialize unsupported execution event "%s".', $event::class),
            ),
        };
    }

    /**
     * Rebuild {@see ExecutionMetadata} from its scalar map.
     *
     * @param array<string, mixed> $data
     */
    private function metadataFrom(array $data): ExecutionMetadata
    {
        $userId = $data['userId'] ?? null;
        $labels = $data['labels'] ?? [];

        /** @var array<string, string> $normalizedLabels */
        $normalizedLabels = is_array($labels) ? $labels : [];

        return new ExecutionMetadata(
            tenantId: TenantId::fromString($this->string($data, 'tenantId')),
            userId: is_string($userId) ? UserId::fromString($userId) : null,
            departmentRef: $this->nullableString($data, 'departmentRef'),
            managerRef: $this->nullableString($data, 'managerRef'),
            intentRef: $this->string($data, 'intentRef'),
            labels: $normalizedLabels,
        );
    }

    /**
     * Rebuild a {@see RetryPolicy} from its scalar map.
     *
     * @param array<string, mixed> $data
     */
    private function retryPolicyFrom(array $data): RetryPolicy
    {
        return new RetryPolicy(
            maxAttempts: $this->int($data, 'maxAttempts'),
            baseDelayMs: $this->int($data, 'baseDelayMs'),
            backoff: BackoffStrategy::from($this->string($data, 'backoff')),
            jitter: (bool) ($data['jitter'] ?? false),
        );
    }

    /**
     * Rebuild a {@see TimeoutPolicy} from its scalar map.
     *
     * @param array<string, mixed> $data
     */
    private function timeoutPolicyFrom(array $data): TimeoutPolicy
    {
        return new TimeoutPolicy(
            wallClockMs: $this->int($data, 'wallClockMs'),
            perStepMs: $this->int($data, 'perStepMs'),
        );
    }

    /**
     * Rebuild the assigned-step descriptors from their scalar list.
     *
     * @param array<array-key, mixed> $data
     *
     * @return list<array{stepId: string, name: string, workerRef: string}>
     */
    private function stepsFrom(array $data): array
    {
        $steps = [];
        foreach ($data as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $steps[] = [
                'stepId' => $this->string($entry, 'stepId'),
                'name' => $this->string($entry, 'name'),
                'workerRef' => $this->string($entry, 'workerRef'),
            ];
        }

        return $steps;
    }

    /**
     * Rebuild a {@see WorkerResult} from its scalar map.
     *
     * @param array<string, mixed> $data
     */
    private function workerResultFrom(array $data): WorkerResult
    {
        return new WorkerResult(
            taskResult: $this->array($data, 'taskResult'),
            evidence: $this->evidenceFrom($this->array($data, 'evidence')),
            reasoningSummary: $this->string($data, 'reasoningSummary'),
            confidence: (float) ($data['confidence'] ?? 0.0),
            executionTimeMs: $this->int($data, 'executionTimeMs'),
            executionCostMicros: $this->int($data, 'executionCostMicros'),
            resourcesUsed: $this->array($data, 'resourcesUsed'),
            automationSelected: $this->nullableString($data, 'automationSelected'),
            toolsUsed: $this->stringList($data, 'toolsUsed'),
            warnings: $this->stringList($data, 'warnings'),
            errors: $this->stringList($data, 'errors'),
            recommendations: $this->stringList($data, 'recommendations'),
            logs: $this->stringList($data, 'logs'),
        );
    }

    /**
     * Rebuild the evidence list from its scalar representation.
     *
     * @param array<array-key, mixed> $data
     *
     * @return list<EvidenceItem>
     */
    private function evidenceFrom(array $data): array
    {
        $items = [];
        foreach ($data as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $items[] = new EvidenceItem(
                $this->string($entry, 'kind'),
                $this->string($entry, 'reference'),
                $this->string($entry, 'summary'),
                new DateTimeImmutable($this->string($entry, 'capturedAt')),
            );
        }

        return $items;
    }

    /**
     * Read a required string field.
     *
     * @param array<array-key, mixed> $data
     */
    private function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * Read an optional string field, mapping empty/missing to null.
     *
     * @param array<array-key, mixed> $data
     */
    private function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Read a required integer field.
     *
     * @param array<array-key, mixed> $data
     */
    private function int(array $data, string $key): int
    {
        $value = $data[$key] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Read a required array field.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function array(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * Read a field as a list of strings, dropping non-string entries.
     *
     * @param array<array-key, mixed> $data
     *
     * @return list<string>
     */
    private function stringList(array $data, string $key): array
    {
        $value = $data[$key] ?? [];
        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $strings[] = $item;
            }
        }

        return $strings;
    }
}
