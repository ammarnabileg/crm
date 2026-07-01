<?php

declare(strict_types=1);

namespace Nizam\Runtime\Infrastructure\Persistence\Pdo;

use DateTimeImmutable;
use Nizam\Platform\Support\Json;
use Nizam\Platform\Support\Uuid;
use Nizam\Runtime\Execution\Domain\Execution;
use Nizam\Runtime\Execution\Domain\ValueObject\EvidenceItem;
use Nizam\Runtime\Orchestration\ValueObject\ManagerDecision;
use PDO;

/**
 * Projects a persisted {@see Execution} — and, when present, its {@see ManagerDecision} — into the
 * queryable child tables the Execution Monitor and reporting read from.
 *
 * The `executions` snapshot and the append-only `execution_history` are the aggregate's own storage; the
 * remaining tables (`execution_timeline`, `execution_logs`, `worker_results`, `execution_metrics`,
 * `evidence`, `manager_decisions`) are denormalized read-side projections. Keeping them out of the
 * event-sourced write path lets the projection be rebuilt idempotently: each write for an execution first
 * clears that execution's projected rows, then re-inserts them from the current aggregate state, so
 * re-projecting after a resume never duplicates. Every row is tenant-scoped and carries audit columns.
 * This writer performs SQL only; it derives nothing the aggregate has not already computed.
 */
final class ExecutionProjectionWriter
{
    /**
     * @param PDO $connection The database connection (SQLite or PostgreSQL).
     */
    public function __construct(
        private readonly PDO $connection,
    ) {
    }

    /**
     * Rebuild an execution's read-side projections from its current state and (optional) manager decision.
     *
     * @param Execution            $execution The persisted execution to project.
     * @param ManagerDecision|null $decision  The manager's verdict, when one was reached.
     * @param DateTimeImmutable    $now       The projection instant, used for audit columns.
     */
    public function project(Execution $execution, ?ManagerDecision $decision, DateTimeImmutable $now): void
    {
        $executionId = $execution->executionId()->toString();
        $tenantId = $execution->metadata()->tenantId()->toString();
        $nowIso = $now->format(DateTimeImmutable::ATOM);

        $this->clear($executionId);
        $this->projectTimeline($executionId, $tenantId, $execution, $nowIso);
        $this->projectWorkerResults($executionId, $tenantId, $execution, $nowIso);
        $this->projectMetrics($executionId, $tenantId, $execution, $nowIso);
        $this->projectEvidence($executionId, $tenantId, $execution, $nowIso);

        if ($decision !== null) {
            $this->projectManagerDecision($executionId, $tenantId, $execution, $decision, $nowIso);
        }
    }

    /**
     * Delete every projected row for an execution so a re-projection is idempotent.
     */
    private function clear(string $executionId): void
    {
        foreach (
            ['execution_timeline', 'execution_logs', 'worker_results', 'execution_metrics', 'evidence', 'manager_decisions'] as $table
        ) {
            $statement = $this->connection->prepare(
                sprintf('DELETE FROM %s WHERE execution_id = :execution_id', $table),
            );
            $statement->execute(['execution_id' => $executionId]);
        }
    }

    /**
     * Insert one timeline row per state the execution entered, in order.
     */
    private function projectTimeline(string $executionId, string $tenantId, Execution $execution, string $nowIso): void
    {
        $insert = $this->connection->prepare(
            'INSERT INTO execution_timeline
                (id, tenant_id, execution_id, sequence_no, state, note, entered_at, created_at)
             VALUES
                (:id, :tenant_id, :execution_id, :sequence_no, :state, :note, :entered_at, :created_at)',
        );

        foreach ($execution->timeline()->entries() as $sequence => $entry) {
            $insert->execute([
                'id' => Uuid::v7(),
                'tenant_id' => $tenantId,
                'execution_id' => $executionId,
                'sequence_no' => $sequence,
                'state' => $entry->state()->value,
                'note' => $entry->note(),
                'entered_at' => $entry->at()->format(DateTimeImmutable::ATOM),
                'created_at' => $nowIso,
            ]);
        }
    }

    /**
     * Insert one worker-result row per completed step (plus its friendly log lines).
     */
    private function projectWorkerResults(string $executionId, string $tenantId, Execution $execution, string $nowIso): void
    {
        $insertResult = $this->connection->prepare(
            'INSERT INTO worker_results
                (id, tenant_id, execution_id, step_id, worker_ref, confidence, execution_time_ms,
                 execution_cost_micros, task_result, reasoning_summary, resources_used, automation_selected,
                 tools_used, warnings, errors, recommendations, logs, produced_at, created_at)
             VALUES
                (:id, :tenant_id, :execution_id, :step_id, :worker_ref, :confidence, :execution_time_ms,
                 :execution_cost_micros, :task_result, :reasoning_summary, :resources_used, :automation_selected,
                 :tools_used, :warnings, :errors, :recommendations, :logs, :produced_at, :created_at)',
        );
        $insertLog = $this->connection->prepare(
            'INSERT INTO execution_logs
                (id, tenant_id, execution_id, level, message, logged_at, created_at)
             VALUES
                (:id, :tenant_id, :execution_id, :level, :message, :logged_at, :created_at)',
        );

        foreach ($execution->steps() as $step) {
            $result = $step->result();
            if ($result === null) {
                continue;
            }

            $producedAt = ($step->finishedAt() ?? $execution->updatedAt())->format(DateTimeImmutable::ATOM);
            $insertResult->execute([
                'id' => Uuid::v7(),
                'tenant_id' => $tenantId,
                'execution_id' => $executionId,
                'step_id' => $step->stepId()->toString(),
                'worker_ref' => $step->workerRef(),
                'confidence' => $result->confidence(),
                'execution_time_ms' => $result->executionTimeMs(),
                'execution_cost_micros' => $result->executionCostMicros(),
                'task_result' => Json::encode($result->taskResult()),
                'reasoning_summary' => $result->reasoningSummary(),
                'resources_used' => Json::encode($result->resourcesUsed()),
                'automation_selected' => $result->automationSelected(),
                'tools_used' => Json::encode($result->toolsUsed()),
                'warnings' => Json::encode($result->warnings()),
                'errors' => Json::encode($result->errors()),
                'recommendations' => Json::encode($result->recommendations()),
                'logs' => Json::encode($result->logs()),
                'produced_at' => $producedAt,
                'created_at' => $nowIso,
            ]);

            foreach ($result->logs() as $line) {
                $insertLog->execute([
                    'id' => Uuid::v7(),
                    'tenant_id' => $tenantId,
                    'execution_id' => $executionId,
                    'level' => 'info',
                    'message' => $line,
                    'logged_at' => $producedAt,
                    'created_at' => $nowIso,
                ]);
            }
        }
    }

    /**
     * Insert the rolled-up cost/performance metrics row for the execution.
     */
    private function projectMetrics(string $executionId, string $tenantId, Execution $execution, string $nowIso): void
    {
        $cost = $execution->cost();
        $performance = $execution->performance();

        $insert = $this->connection->prepare(
            'INSERT INTO execution_metrics
                (id, tenant_id, execution_id, tokens, currency_micros, wall_ms, cpu_ms, step_count,
                 retry_count, provider_breakdown, captured_at, created_at)
             VALUES
                (:id, :tenant_id, :execution_id, :tokens, :currency_micros, :wall_ms, :cpu_ms, :step_count,
                 :retry_count, :provider_breakdown, :captured_at, :created_at)',
        );
        $insert->execute([
            'id' => Uuid::v7(),
            'tenant_id' => $tenantId,
            'execution_id' => $executionId,
            'tokens' => $cost->tokens(),
            'currency_micros' => $cost->currencyMicros(),
            'wall_ms' => $performance->wallMs(),
            'cpu_ms' => $performance->cpuMs(),
            'step_count' => $performance->stepCount(),
            'retry_count' => $performance->retryCount(),
            'provider_breakdown' => Json::encode($cost->providerBreakdown()),
            'captured_at' => $nowIso,
            'created_at' => $nowIso,
        ]);
    }

    /**
     * Insert the de-duplicated evidence artefacts drawn from every step's worker result.
     */
    private function projectEvidence(string $executionId, string $tenantId, Execution $execution, string $nowIso): void
    {
        $insert = $this->connection->prepare(
            'INSERT INTO evidence
                (id, tenant_id, execution_id, kind, reference, summary, captured_at, created_at)
             VALUES
                (:id, :tenant_id, :execution_id, :kind, :reference, :summary, :captured_at, :created_at)',
        );

        $seen = [];
        foreach ($execution->steps() as $step) {
            $result = $step->result();
            if ($result === null) {
                continue;
            }

            foreach ($result->evidence() as $item) {
                $key = $item->dedupeKey();
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $insert->execute([
                    'id' => Uuid::v7(),
                    'tenant_id' => $tenantId,
                    'execution_id' => $executionId,
                    'kind' => $item->kind(),
                    'reference' => $item->reference(),
                    'summary' => $item->summary(),
                    'captured_at' => $item->capturedAt()->format(DateTimeImmutable::ATOM),
                    'created_at' => $nowIso,
                ]);
            }
        }
    }

    /**
     * Insert the manager's decision row for the execution.
     */
    private function projectManagerDecision(
        string $executionId,
        string $tenantId,
        Execution $execution,
        ManagerDecision $decision,
        string $nowIso,
    ): void {
        $insert = $this->connection->prepare(
            'INSERT INTO manager_decisions
                (id, tenant_id, execution_id, manager_ref, outcome, summary, confidence_score,
                 confidence, evidence, merged_result, decided_at, created_at)
             VALUES
                (:id, :tenant_id, :execution_id, :manager_ref, :outcome, :summary, :confidence_score,
                 :confidence, :evidence, :merged_result, :decided_at, :created_at)',
        );
        $insert->execute([
            'id' => Uuid::v7(),
            'tenant_id' => $tenantId,
            'execution_id' => $executionId,
            'manager_ref' => $execution->metadata()->managerRef() ?? 'unknown',
            'outcome' => $decision->outcome()->value,
            'summary' => $decision->summary(),
            'confidence_score' => $decision->confidence()->score(),
            'confidence' => Json::encode($decision->confidence()->toArray()),
            'evidence' => Json::encode(array_map(
                static fn (EvidenceItem $item): array => $item->toArray(),
                $decision->evidence(),
            )),
            'merged_result' => Json::encode($decision->mergedResult()->toArray()),
            'decided_at' => $nowIso,
            'created_at' => $nowIso,
        ]);
    }
}
