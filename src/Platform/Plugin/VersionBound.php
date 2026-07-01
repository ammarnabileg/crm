<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin;

/**
 * A single comparison bound within a {@see VersionConstraint}.
 *
 * A bound pairs a comparison operator (`=`, `>`, `>=`, `<`, `<=`) with a reference
 * {@see SemanticVersion}. Constraints such as `^1.2.3` or `>=1.0.0 <2.0.0` expand into a conjunction
 * of bounds; a version satisfies the constraint only when it satisfies every bound. To match
 * conventional range semantics, a pre-release version is admitted by an upper `<` bound only when the
 * bound's reference has the same numeric core *and* itself carries a pre-release tag, so a stable
 * range never accidentally lets an unstable pre-release in through its upper edge.
 */
final class VersionBound
{
    /**
     * @param string          $operator  One of `=`, `>`, `>=`, `<`, `<=`.
     * @param SemanticVersion $reference The version the operator compares against.
     */
    public function __construct(
        private readonly string $operator,
        private readonly SemanticVersion $reference,
    ) {
    }

    /**
     * Whether the candidate version satisfies this bound.
     */
    public function allows(SemanticVersion $candidate): bool
    {
        if ($candidate->isPreRelease() && !$this->admitsPreRelease($candidate)) {
            return false;
        }

        $comparison = $candidate->compareTo($this->reference);

        return match ($this->operator) {
            '=' => $comparison === 0,
            '>' => $comparison > 0,
            '>=' => $comparison >= 0,
            '<' => $comparison < 0,
            '<=' => $comparison <= 0,
        };
    }

    /**
     * The comparison operator.
     */
    public function operator(): string
    {
        return $this->operator;
    }

    /**
     * The reference version.
     */
    public function reference(): SemanticVersion
    {
        return $this->reference;
    }

    /**
     * Whether a pre-release candidate may be considered against this bound at all.
     *
     * A pre-release candidate is only admissible when the reference version is itself a pre-release
     * pinned to the same numeric core (major.minor.patch); otherwise stable ranges silently reject it.
     */
    private function admitsPreRelease(SemanticVersion $candidate): bool
    {
        return $this->reference->isPreRelease()
            && $this->reference->major() === $candidate->major()
            && $this->reference->minor() === $candidate->minor()
            && $this->reference->patch() === $candidate->patch();
    }
}
