<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Workspace;
use App\Models\User;

/**
 * Fired when a workspace (tenant) is provisioned. Listeners handle audit logging
 * and (later) provisioning side effects (e.g. search indexing, onboarding).
 */
final class WorkspaceCreated extends Event
{
    public function __construct(
        public readonly Workspace $workspace,
        public readonly User $owner,
    ) {
        parent::__construct();
    }
}
