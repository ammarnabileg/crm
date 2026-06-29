<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * Lists the workspaces a user belongs to *as a candidate* (applicant), for
 * layers that must stay decoupled from the Recruitment module — e.g. the
 * workspace chooser in the Workspaces module. Recruitment binds the real
 * implementation; Core binds an empty default so the platform works with
 * Recruitment disabled. Mirrors EntitlementResolver (ARCHITECTURE.md §4).
 */
interface CandidateDirectory
{
    /**
     * @return list<array<string, mixed>>  each: id, name, slug, applications_count, last_applied_at
     */
    public function workspacesForCandidate(string $userId): array;

    /** True if the user has applied to (is a candidate in) the given workspace. */
    public function isCandidate(string $workspaceId, string $userId): bool;
}
