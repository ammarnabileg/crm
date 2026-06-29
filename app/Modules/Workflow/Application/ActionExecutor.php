<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workflow\Application;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Contracts\NotificationWriter;
use HaHireAI\Core\Contracts\RecruitmentActions;
use HaHireAI\Core\Contracts\TaskWriter;
use HaHireAI\Modules\AiEngine\Application\AiEngine;

/**
 * Executes a single workflow node. Every effectful node calls ONLY an existing
 * service via its Core contract — the engine embeds no business logic, no AI, no
 * providers, and no raw SQL (docs/WORKFLOW_ENGINE.md §5; ARCHITECTURE.md §4).
 *
 * Node `type` strings come straight from {@see NodeCatalog}. Config values may
 * contain `{{field}}` tokens that the builder's variable picker inserts (the user
 * never writes code); {@see resolve()} substitutes them from the trigger payload.
 */
final class ActionExecutor
{
    public function __construct(
        private readonly AiEngine $ai,
        private readonly AuditRecorder $audit,
        private readonly TaskWriter $tasks,
        private readonly NotificationWriter $notifications,
        private readonly RecruitmentActions $recruitment,
        private readonly WorkflowCollectionService $collections,
    ) {
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array{workspace_id: string, payload: array<string,mixed>, actor: ?string}  $context
     */
    public function execute(string $action, array $params, array $context): string
    {
        $payload = $context['payload'];

        return match ($action) {
            // ── Utilities & back-compat simple actions ─────────────────────────
            'log', 'util.log' => $this->resolve((string) ($params['message'] ?? 'logged'), $payload),
            'noop', 'util.noop' => 'noop',
            'audit', 'util.audit', 'workspace.create_activity'
                => $this->doAudit($context, $this->resolve((string) ($params['message'] ?? 'workflow action'), $payload)),
            'variables.set' => 'set ' . (string) ($params['name'] ?? 'var') . ' = ' . $this->resolve((string) ($params['value'] ?? ''), $payload),

            // ── AI — always through the central AI Engine ──────────────────────
            'run_ai' => $this->doAi($context, (string) ($params['capability'] ?? 'summarize_candidate'), $params['variables'] ?? $payload),
            'ai.recommendation', 'ai.rank_candidate', 'ai.decision'
                => $this->doAi($context, 'candidate_recommendation', $payload),
            'ai.summary', 'ai.summarize_cv', 'ai.evaluate_interview', 'ai.generate_questions', 'ai.translate', 'ai.skill_extraction'
                => $this->doAi($context, 'summarize_candidate', $payload),

            // ── Tasks — via the TaskWriter contract ────────────────────────────
            'workspace.create_task' => $this->doCreateTask($context, $params),

            // ── Notifications — via the NotificationWriter contract ────────────
            'users.notify', 'notify.in_app', 'workspace.create_notification'
                => $this->doNotify($context, $params),

            // ── Recruitment — via the RecruitmentActions contract ──────────────
            'recruitment.move_candidate', 'recruitment.update_stage' => $this->doMoveStage($context, $params),
            'recruitment.reject_candidate' => $this->doSetStatus($context, $params, 'rejected'),
            'recruitment.hire_candidate' => $this->doSetStatus($context, $params, 'hired'),
            'recruitment.archive_candidate' => $this->doSetStatus($context, $params, 'archived'),

            // ── Database — Dynamic Collections (no raw SQL) ────────────────────
            'db.create_record' => $this->doCreateRecord($context, $params),
            'db.count_records' => $this->doCountRecords($context, $params),

            default => $this->unsupported($action),
        };
    }

    /** @param array{workspace_id:string,payload:array<string,mixed>,actor:?string} $context */
    private function doAudit(array $context, string $message): string
    {
        $this->audit->record('workflows.action.audit', [
            'workspace_id' => $context['workspace_id'],
            'actor_user_id' => $context['actor'],
            'entity_type' => 'workflow',
            'changes' => ['message' => $message],
        ]);

        return 'audited: ' . $message;
    }

    /**
     * @param array{workspace_id:string,payload:array<string,mixed>,actor:?string} $context
     * @param array<string,mixed> $variables
     */
    private function doAi(array $context, string $capability, array $variables): string
    {
        $result = $this->ai->run($context['workspace_id'], $capability, $this->scalarize($variables), $context['actor']);

        return 'ai(' . $result->provider . '): ' . mb_substr($result->text, 0, 140);
    }

    /**
     * @param array{workspace_id:string,payload:array<string,mixed>,actor:?string} $context
     * @param array<string,mixed> $params
     */
    private function doCreateTask(array $context, array $params): string
    {
        $payload = $context['payload'];
        $title = $this->resolve((string) ($params['title'] ?? ''), $payload);
        $assignee = $this->resolve((string) ($params['assignee'] ?? $params['assignee_user_id'] ?? ''), $payload);

        $id = $this->tasks->createTask(
            $context['workspace_id'],
            $title !== '' ? $title : 'Workflow task',
            $context['actor'],
            ['assignee_user_id' => $assignee, 'entity_type' => 'workflow'],
        );

        return $id === '' ? 'skipped: Tasks module not installed' : 'task created: ' . ($title !== '' ? $title : 'Workflow task');
    }

    /**
     * @param array{workspace_id:string,payload:array<string,mixed>,actor:?string} $context
     * @param array<string,mixed> $params
     */
    private function doNotify(array $context, array $params): string
    {
        $payload = $context['payload'];
        $userId = $this->resolve((string) ($params['user_id'] ?? $params['to'] ?? ($context['actor'] ?? '')), $payload);
        if ($userId === '') {
            return 'skipped: no recipient';
        }

        $title = $this->resolve((string) ($params['title'] ?? 'Workflow notification'), $payload);
        $body = $this->resolve((string) ($params['body'] ?? ''), $payload);

        $id = $this->notifications->sendNotification($context['workspace_id'], $userId, 'workflow', $title, $body !== '' ? $body : null);

        return $id === '' ? 'skipped: Notifications module not installed' : 'notified ' . $userId;
    }

    /**
     * @param array{workspace_id:string,payload:array<string,mixed>,actor:?string} $context
     * @param array<string,mixed> $params
     */
    private function doMoveStage(array $context, array $params): string
    {
        $payload = $context['payload'];
        $applicationId = $this->resolve((string) ($params['application_id'] ?? ''), $payload);
        $stage = $this->resolve((string) ($params['stage'] ?? ''), $payload);
        if ($applicationId === '' || $stage === '') {
            return 'skipped: application or stage missing';
        }

        $this->recruitment->moveApplicationStage($context['workspace_id'], $applicationId, $stage, $context['actor']);

        return 'moved application ' . $applicationId . ' → ' . $stage;
    }

    /**
     * @param array{workspace_id:string,payload:array<string,mixed>,actor:?string} $context
     * @param array<string,mixed> $params
     */
    private function doSetStatus(array $context, array $params, string $status): string
    {
        $payload = $context['payload'];
        $applicationId = $this->resolve((string) ($params['application_id'] ?? ''), $payload);
        if ($applicationId === '') {
            return 'skipped: application missing';
        }

        $this->recruitment->setApplicationStatus($context['workspace_id'], $applicationId, $status, $context['actor']);

        return 'application ' . $applicationId . ' set ' . $status;
    }

    /**
     * @param array{workspace_id:string,payload:array<string,mixed>,actor:?string} $context
     * @param array<string,mixed> $params
     */
    private function doCreateRecord(array $context, array $params): string
    {
        $payload = $context['payload'];
        $key = $this->resolve((string) ($params['collection'] ?? ''), $payload);
        if ($key === '') {
            return 'skipped: no collection';
        }

        $collection = $this->collections->findByKey($context['workspace_id'], $key);
        if ($collection === null) {
            return 'skipped: collection "' . $key . '" not found';
        }

        $this->collections->createRecord(
            $context['workspace_id'],
            (string) $collection['id'],
            $this->parseFields((string) ($params['fields'] ?? ''), $payload),
            $context['actor'],
        );

        return 'record created in ' . $key;
    }

    /**
     * @param array{workspace_id:string,payload:array<string,mixed>,actor:?string} $context
     * @param array<string,mixed> $params
     */
    private function doCountRecords(array $context, array $params): string
    {
        $key = $this->resolve((string) ($params['collection'] ?? ''), $context['payload']);
        $collection = $key === '' ? null : $this->collections->findByKey($context['workspace_id'], $key);
        if ($collection === null) {
            return 'skipped: collection not found';
        }

        return 'count(' . $key . ') = ' . $this->collections->countRecords($context['workspace_id'], (string) $collection['id']);
    }

    /**
     * Nodes whose execution needs an external provider (email/SMTP, Slack/Teams,
     * outbound HTTP) or deferred scheduling (delays, waits) that this deployment
     * has not configured. Reported transparently instead of silently "succeeding".
     */
    private function unsupported(string $action): string
    {
        return 'skipped: "' . $action . '" has no configured provider in this deployment';
    }

    /**
     * Substitute `{{field}}` tokens with values from the trigger payload. These
     * tokens are produced by the builder's variable picker, never typed by users.
     *
     * @param  array<string,mixed>  $payload
     */
    private function resolve(string $value, array $payload): string
    {
        if (! str_contains($value, '{{')) {
            return $value;
        }

        return (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', static function (array $m) use ($payload): string {
            $v = $payload[$m[1]] ?? '';

            return is_scalar($v) ? (string) $v : (string) json_encode($v);
        }, $value);
    }

    /**
     * Parse "key: value, key2: value2" field strings (built by the UI) into a map,
     * resolving any tokens. No JSON or code — a simple, safe key/value list.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,string>
     */
    private function parseFields(string $raw, array $payload): array
    {
        $out = [];
        foreach (explode(',', $raw) as $pair) {
            if (! str_contains($pair, ':')) {
                continue;
            }
            [$k, $v] = explode(':', $pair, 2);
            $k = trim($k);
            if ($k !== '') {
                $out[$k] = $this->resolve(trim($v), $payload);
            }
        }

        return $out;
    }

    /**
     * @param  array<string,mixed>  $variables
     * @return array<string, scalar|null>
     */
    private function scalarize(array $variables): array
    {
        $out = [];
        foreach ($variables as $key => $value) {
            $out[$key] = is_scalar($value) || $value === null ? $value : json_encode($value);
        }

        return $out;
    }
}
