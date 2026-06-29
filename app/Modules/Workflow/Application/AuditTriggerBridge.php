<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workflow\Application;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Contracts\EventDispatcher;

/**
 * Turns the audit trail into workflow triggers WITHOUT modifying any existing
 * service: it decorates the AuditRecorder contract, records the audit entry as
 * before, then re-publishes it on the event bus as `audit.{action}` so the
 * Workflow Engine can react (e.g. "When an interview is evaluated…"). Every
 * controller already records audit through this one contract, so this captures the
 * whole lifecycle for free (ARCHITECTURE.md §4; docs/WORKFLOW_EVENTS.md).
 */
final class AuditTriggerBridge implements AuditRecorder
{
    public function __construct(
        private readonly AuditRecorder $inner,
        private readonly EventDispatcher $events,
    ) {
    }

    /**
     * @param  array{workspace_id?: ?string, actor_user_id?: ?string, entity_type?: ?string, entity_id?: ?string, ip?: ?string, changes?: array<string,mixed>}  $context
     */
    public function record(string $action, array $context = []): void
    {
        $this->inner->record($action, $context);

        // Never re-trigger on the engine's own audit actions — prevents a workflow
        // whose action writes audit from triggering itself in a loop.
        if (str_starts_with($action, 'workflows.')) {
            return;
        }

        $workspaceId = (string) ($context['workspace_id'] ?? '');
        if ($workspaceId === '') {
            return; // workspace-scoped triggers only
        }

        $payload = [
            'workspace_id' => $workspaceId,
            'user_id' => $context['actor_user_id'] ?? null,
            'entity_type' => $context['entity_type'] ?? null,
            'entity_id' => $context['entity_id'] ?? null,
        ];

        // Alias the entity id by type so node configs can use {{application_id}},
        // {{offer_id}}, {{workspace_id}}, … without knowing the audit shape.
        $type = (string) ($context['entity_type'] ?? '');
        if ($type !== '' && ! empty($context['entity_id'])) {
            $payload[$type . '_id'] = $context['entity_id'];
        }

        // Flatten scalar "changes" so nodes can read them (e.g. {{to_status}}).
        foreach ((array) ($context['changes'] ?? []) as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $payload[(string) $key] = $value;
            }
        }

        $this->events->dispatch('audit.' . $action, $payload);
    }
}
