<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Pool;
use App\Models\PoolCandidate;
use App\Services\Ats\TalentPool;
use Tests\TestCase;

/**
 * Talent Pool service (docs/53 ATS Talent Pools). Covers pool creation, idempotent
 * candidate add/remove, the denormalised candidate_count bookkeeping, and the
 * tenant-scoped listings. All rows are built in-tx and rolled back.
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function setUp(): void
    {
        tenant()->setById((int) app('db')->table('workspaces')->orderBy('id')->value('id'));
    }

    private function service(): TalentPool
    {
        return new TalentPool();
    }

    private function userId(): int
    {
        return (int) app('db')->table('users')->orderBy('id')->value('id');
    }

    private function secondUserId(): int
    {
        $id = app('db')->table('users')->orderBy('id', 'asc')->limit(1)->offset(1)->value('id');

        return $id !== null ? (int) $id : $this->userId();
    }

    public function test_create_pool_persists_with_tenant_and_zero_count(): void
    {
        $pool = $this->service()->createPool('Backend Talent', ['description' => 'Senior backend folks']);

        $this->assertInstanceOf(Pool::class, $pool);
        $this->assertNotNull($pool->getKey());
        $this->assertSame('Backend Talent', (string) $pool->name);
        $this->assertSame(0, (int) $pool->candidate_count);
        $this->assertSame((int) tenant()->id(), (int) $pool->workspace_id);
        $this->assertNotNull($pool->slug);
    }

    public function test_create_pool_generates_unique_slugs(): void
    {
        $a = $this->service()->createPool('Sales Team');
        $b = $this->service()->createPool('Sales Team');

        $this->assertFalse((string) $a->slug === (string) $b->slug);
    }

    public function test_add_candidate_increments_count_and_persists_membership(): void
    {
        $svc = $this->service();
        $pool = $svc->createPool('Pool A');
        $poolId = (int) $pool->getKey();

        $member = $svc->addCandidate($poolId, $this->userId(), ['added_by' => $this->userId()]);

        $this->assertInstanceOf(PoolCandidate::class, $member);
        $this->assertSame($poolId, (int) $member->pool_id);
        $this->assertSame($this->userId(), (int) $member->user_id);

        $fresh = Pool::find($poolId);
        $this->assertSame(1, (int) $fresh->candidate_count);
        $this->assertSame(1, count($svc->listCandidates($poolId)));
    }

    public function test_add_candidate_is_idempotent(): void
    {
        $svc = $this->service();
        $poolId = (int) $svc->createPool('Pool B')->getKey();

        $svc->addCandidate($poolId, $this->userId());
        $svc->addCandidate($poolId, $this->userId()); // duplicate

        $this->assertSame(1, count($svc->listCandidates($poolId)));
        $this->assertSame(1, (int) Pool::find($poolId)->candidate_count);
    }

    public function test_remove_candidate_decrements_count(): void
    {
        $svc = $this->service();
        $poolId = (int) $svc->createPool('Pool C')->getKey();
        $svc->addCandidate($poolId, $this->userId());

        $removed = $svc->removeCandidate($poolId, $this->userId());

        $this->assertTrue($removed);
        $this->assertSame(0, count($svc->listCandidates($poolId)));
        $this->assertSame(0, (int) Pool::find($poolId)->candidate_count);
    }

    public function test_remove_absent_candidate_returns_false(): void
    {
        $svc = $this->service();
        $poolId = (int) $svc->createPool('Pool D')->getKey();

        $this->assertFalse($svc->removeCandidate($poolId, $this->userId()));
    }

    public function test_count_tracks_multiple_candidates(): void
    {
        $svc = $this->service();
        $poolId = (int) $svc->createPool('Pool E')->getKey();

        $svc->addCandidate($poolId, $this->userId());
        $svc->addCandidate($poolId, $this->secondUserId());

        // With distinct users the count is 2; if the env has a single user it is 1.
        $expected = $this->userId() === $this->secondUserId() ? 1 : 2;
        $this->assertSame($expected, (int) Pool::find($poolId)->candidate_count);
    }

    public function test_pools_lists_tenant_pools(): void
    {
        $svc = $this->service();
        $before = count($svc->pools());
        $svc->createPool('Listed Pool');

        $this->assertSame($before + 1, count($svc->pools()));
    }
};
