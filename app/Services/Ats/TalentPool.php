<?php

declare(strict_types=1);

namespace App\Services\Ats;

use App\Models\Pool;
use App\Models\PoolCandidate;

/**
 * Talent Pool management (docs/53 ATS Talent Pools).
 *
 * Recruiters group candidates into named, tenant-scoped pools they can source and
 * nurture independently of any single job. This service owns the membership
 * lifecycle and keeps the denormalised `pools.candidate_count` accurate
 * (incremented on add, decremented on remove). All reads/writes are tenant-scoped
 * through the models; the active tenant is stamped automatically on insert.
 */
final class TalentPool
{
    /**
     * Create a pool in the current tenant.
     *
     * @param array<string,mixed> $attrs Optional: description, type_id, owner_user_id,
     *                                    department_id, is_shared, created_by.
     */
    public function createPool(string $name, array $attrs = []): Pool
    {
        return Pool::create([
            'name'            => $name,
            'slug'            => $this->uniqueSlug($name),
            'description'     => $attrs['description'] ?? null,
            'type_id'         => $attrs['type_id'] ?? null,
            'owner_user_id'   => $attrs['owner_user_id'] ?? null,
            'department_id'   => $attrs['department_id'] ?? null,
            'is_shared'       => (int) (bool) ($attrs['is_shared'] ?? false),
            'candidate_count' => 0,
            'created_by'      => $attrs['created_by'] ?? null,
        ]);
    }

    /**
     * Add a candidate (by user id) to a pool. Idempotent: a candidate already in
     * the pool is not duplicated and the count is not double-incremented.
     *
     * @param array<string,mixed> $attrs Optional: pool_group_id, source_id, stage_id, added_by.
     */
    public function addCandidate(int $poolId, int $userId, array $attrs = []): PoolCandidate
    {
        $existing = PoolCandidate::query()
            ->where('pool_id', '=', $poolId)
            ->where('user_id', '=', $userId)
            ->first();

        if ($existing !== null) {
            return PoolCandidate::hydrate($existing);
        }

        $member = PoolCandidate::create([
            'pool_id'       => $poolId,
            'pool_group_id' => $attrs['pool_group_id'] ?? null,
            'user_id'       => $userId,
            'source_id'     => $attrs['source_id'] ?? null,
            'stage_id'      => $attrs['stage_id'] ?? null,
            'added_by'      => $attrs['added_by'] ?? null,
            'added_at'      => $attrs['added_at'] ?? now(),
        ]);

        $this->recountPool($poolId);

        return $member;
    }

    /**
     * Remove a candidate from a pool (soft-delete the membership) and decrement
     * the cached count. Returns true if a membership was removed.
     */
    public function removeCandidate(int $poolId, int $userId): bool
    {
        $existing = PoolCandidate::query()
            ->where('pool_id', '=', $poolId)
            ->where('user_id', '=', $userId)
            ->first();

        if ($existing === null) {
            return false;
        }

        PoolCandidate::hydrate($existing)->delete();
        $this->recountPool($poolId);

        return true;
    }

    /**
     * Active (non-removed) memberships of a pool, oldest first.
     *
     * @return PoolCandidate[]
     */
    public function listCandidates(int $poolId): array
    {
        return array_map(
            [PoolCandidate::class, 'hydrate'],
            PoolCandidate::query()
                ->where('pool_id', '=', $poolId)
                ->orderBy('added_at', 'asc')
                ->orderBy('id', 'asc')
                ->get()
        );
    }

    /**
     * All pools in the current tenant, newest first.
     *
     * @return Pool[]
     */
    public function pools(): array
    {
        return array_map(
            [Pool::class, 'hydrate'],
            Pool::query()->orderBy('id', 'desc')->get()
        );
    }

    /**
     * Recompute `candidate_count` from the live membership rows. Authoritative —
     * avoids drift from concurrent add/remove and keeps the denormalised count
     * exactly equal to the number of active members.
     */
    private function recountPool(int $poolId): void
    {
        $count = PoolCandidate::query()->where('pool_id', '=', $poolId)->count();

        $pool = Pool::find($poolId);
        if ($pool !== null) {
            $pool->update(['candidate_count' => $count]);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = slugify($name);
        $slug = $base;
        $i = 1;

        // Slug is unique per tenant (query() is tenant-scoped); suffix on collision.
        while (Pool::query()->where('slug', '=', $slug)->first() !== null) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }
}
