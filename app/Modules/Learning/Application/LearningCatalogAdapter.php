<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Learning\Application;

use HaHireAI\Core\Contracts\LearningCatalog;

/**
 * Adapts the Learning module's services to the public {@see LearningCatalog}
 * contract other modules depend on (ARCHITECTURE.md §4). Keeps the read surface
 * and the enrollment hook in one sanctioned place so callers never reach into
 * the Learning tables or concrete services.
 */
final class LearningCatalogAdapter implements LearningCatalog
{
    public function __construct(
        private readonly ProgramService $programs,
        private readonly EnrollmentService $enrollments,
    ) {
    }

    public function publishedPrograms(string $workspaceId, int $limit = 50): array
    {
        return $this->programs->publishedPrograms($workspaceId, $limit);
    }

    public function countPrograms(string $workspaceId): int
    {
        return $this->programs->countPrograms($workspaceId);
    }

    public function enrollUser(string $workspaceId, string $programId, string $userId): bool
    {
        return $this->enrollments->enroll($workspaceId, $programId, $userId);
    }
}
