<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\Audit\AuditLogger;
use App\Events\WorkspaceCreated;

/**
 * Records an audit entry (with the new workspace's values) when a workspace is
 * provisioned. Side effect of the WorkspaceCreated event (docs/47 EAS-6/EAS-7).
 */
final class RecordWorkspaceCreated
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function __invoke(WorkspaceCreated $event): void
    {
        $workspace = $event->workspace;

        $this->audit->logChange('workspace.created', null, [
            'name'              => $workspace->name,
            'slug'              => $workspace->slug,
            'workspace_status_id' => $workspace->getAttribute('workspace_status_id'),
        ], [
            'workspace_id'   => (int) $workspace->getKey(),
            'user_id'      => (int) $event->owner->getKey(),
            'subject_type' => 'workspace',
            'subject_id'   => (int) $workspace->getKey(),
            'description'  => 'Created workspace "' . $workspace->name . '"',
        ]);
    }
}
