<?php

declare(strict_types=1);

namespace HaHireAI\Core\Learning;

use HaHireAI\Core\Contracts\LearningCatalog;

/**
 * Permissive null default for {@see LearningCatalog}, bound in Core so consumers
 * (e.g. the Workflow ActionExecutor's "enroll in program" action) resolve even
 * when the Learning module is disabled. The Learning module overrides this with
 * the real adapter at boot (ARCHITECTURE.md §4 — graceful degradation).
 */
final class NullLearningCatalog implements LearningCatalog
{
    public function publishedPrograms(string $workspaceId, int $limit = 50): array
    {
        return [];
    }

    public function countPrograms(string $workspaceId): int
    {
        return 0;
    }

    public function enrollUser(string $workspaceId, string $programId, string $userId): bool
    {
        return false; // no Learning module → nothing enrolled (action reports "skipped")
    }
}
