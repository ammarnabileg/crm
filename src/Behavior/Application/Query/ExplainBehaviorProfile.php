<?php

declare(strict_types=1);

namespace Nizam\Behavior\Application\Query;

use Nizam\Kernel\Application\Query;

/**
 * Request to explain what a role's approved practice would change about its behavior profile.
 *
 * Answered with a list of {@see \Nizam\Behavior\Application\Dto\RecommendationView}s: one per trait
 * the approved evidence supports changing, each carrying its reason, supporting evidence, confidence,
 * and how-to guidance. This is the engine's transparency surface — a read-only "why", producing no
 * proposal and mutating nothing. An empty list means the current profile already matches the
 * approved practice. Tenant-scoped.
 */
final class ExplainBehaviorProfile implements Query
{
    /**
     * @param string      $tenantId  The owning tenant's identifier.
     * @param string      $profileId The profile to explain.
     * @param string|null $since     Optional ISO-8601 lower bound; only later approved practice is considered.
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $profileId,
        public readonly ?string $since = null,
    ) {
    }
}
