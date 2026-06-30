<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Contracts;

/**
 * One pluggable social-data source (GitHub, StackOverflow, a personal website, …).
 * Adapters live ONLY in the Integration Platform; adding a new source means
 * adding one of these and registering it — the Recruitment module and the
 * scoring engine never change (the whole point of the design).
 *
 * An adapter MUST be resilient: on any failure (blocked, 404, rate-limited,
 * offline) it returns an "unreachable" snapshot, never throws.
 *
 * fetch() returns the normalised envelope described in
 * {@see \HaHireAI\Core\Contracts\SocialProfileProbe} (minus the 'url', which the
 * probe service fills in).
 */
interface SocialAdapter
{
    /** Stable adapter key, e.g. "github". */
    public function key(): string;

    /** Canonical platform name reported in the snapshot, e.g. "github". */
    public function platform(): string;

    /** Can this adapter handle the given URL (by host)? */
    public function supports(string $url): bool;

    /** @return array<string, mixed> the snapshot envelope (no 'url' key) */
    public function fetch(string $url): array;
}
