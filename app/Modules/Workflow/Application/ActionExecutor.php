<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workflow\Application;

use HaHireAI\Modules\AiEngine\Application\AiEngine;
use HaHireAI\Core\Contracts\AuditRecorder;

/**
 * Executes a single workflow action. AI steps go through the AI Engine — the
 * Workflow Engine never embeds AI or providers (docs/WORKFLOW_ENGINE.md §5).
 */
final class ActionExecutor
{
    public function __construct(
        private readonly AiEngine $ai,
        private readonly AuditRecorder $audit,
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
            'log' => (string) ($params['message'] ?? 'logged'),

            'audit' => $this->doAudit($context, (string) ($params['message'] ?? 'workflow action')),

            'run_ai' => $this->doAi($context, (string) ($params['capability'] ?? 'summarize_candidate'), $params['variables'] ?? $payload),

            default => 'noop: unknown action [' . $action . ']',
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
