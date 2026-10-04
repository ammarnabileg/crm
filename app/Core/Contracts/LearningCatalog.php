<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * The sanctioned read surface of the Learning module for OTHER modules
 * (ARCHITECTURE.md §4). It is the seam through which Recruitment (or a future
 * Employees/Onboarding module) will link learning programs to a candidate or a
 * newly-hired employee — without ever touching the Learning tables directly.
 *
 * Bound to the Learning module's ProgramService at boot. Intentionally small for
 * now; it grows as candidate/employee integration is switched on.
 */
interface LearningCatalog
{
    /** @return list<array<string, mixed>> published programs in a workspace */
    public function publishedPrograms(string $workspaceId, int $limit = 50): array;

    /** Number of programs in a workspace (any status, not deleted). */
    public function countPrograms(string $workspaceId): int;

    /** Enroll a user into a program (idempotent) — the future candidate/onboarding hook. */
    public function enrollUser(string $workspaceId, string $programId, string $userId): bool;
}
