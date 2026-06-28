<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Contracts\CandidateDirectory;

/**
 * Bridges the Core CandidateDirectory contract to the Recruitment CandidacyService,
 * so decoupled layers (the workspace chooser) can list a user's candidate
 * workspaces without depending on Recruitment internals (ARCHITECTURE.md §4).
 */
final class CandidateDirectoryAdapter implements CandidateDirectory
{
    public function __construct(private readonly CandidacyService $candidacy)
    {
    }

    public function workspacesForCandidate(string $userId): array
    {
        return $this->candidacy->workspacesForCandidate($userId);
    }

    public function isCandidate(string $workspaceId, string $userId): bool
    {
        return $this->candidacy->isCandidate($workspaceId, $userId);
    }
}
