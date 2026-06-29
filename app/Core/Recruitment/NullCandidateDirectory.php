<?php

declare(strict_types=1);

namespace HaHireAI\Core\Recruitment;

use HaHireAI\Core\Contracts\CandidateDirectory;

/**
 * The default directory: nobody is a candidate anywhere. Active when the
 * Recruitment module is disabled. Recruitment overrides this with a real adapter.
 */
final class NullCandidateDirectory implements CandidateDirectory
{
    public function workspacesForCandidate(string $userId): array
    {
        return [];
    }

    public function isCandidate(string $workspaceId, string $userId): bool
    {
        return false;
    }
}
