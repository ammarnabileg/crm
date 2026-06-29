<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Navigation\Application;

use HaHireAI\Core\Contracts\AccessControl;
use HaHireAI\Core\Contracts\CandidateDirectory;
use HaHireAI\Core\Contracts\MemberDirectory;
use HaHireAI\Modules\Authentication\Application\AuthContext;

/**
 * Builds the header context switcher model — the single control that moves a
 * user between everything they can be on the unified surface: the HaHireAI
 * platform panel (if they have any platform permission), the workspaces they
 * staff, and the workspaces they're a candidate in. One identity, many contexts
 * (docs/NAVIGATION_ARCHITECTURE.md). Reads only Core contracts, so it stays
 * decoupled from the owning modules (ARCHITECTURE.md §4).
 */
final class ContextSwitcher
{
    public function __construct(
        private readonly AuthContext $auth,
        private readonly MemberDirectory $members,
        private readonly CandidateDirectory $candidates,
        private readonly AccessControl $access,
    ) {
    }

    /**
     * @param  string  $currentType  the context the page being rendered is in:
     *                               'staff' | 'candidate' | 'platform'
     * @return array{
     *   staff: list<array{id: string, name: string}>,
     *   candidate: list<array{id: string, name: string}>,
     *   hasPlatform: bool,
     *   currentType: string,
     *   currentWorkspaceId: ?string,
     *   currentLabel: string
     * }
     */
    public function model(string $currentType): array
    {
        $uid = (string) $this->auth->id();
        $currentWs = $this->auth->currentWorkspaceId();
        if ($uid === '') {
            return [
                'staff' => [], 'candidate' => [], 'hasPlatform' => false,
                'currentType' => $currentType, 'currentWorkspaceId' => $currentWs,
                'currentLabel' => 'HaHireAI',
            ];
        }

        $shape = static fn (array $w): array => ['id' => (string) $w['id'], 'name' => (string) $w['name']];
        $staff = array_map($shape, $this->members->workspacesForUser($uid));
        $candidate = array_map($shape, $this->candidates->workspacesForCandidate($uid));
        $hasPlatform = $this->access->systemPermissionsForUser($uid) !== [];

        $label = 'HaHireAI';
        if ($currentType !== 'platform') {
            foreach ([...$staff, ...$candidate] as $w) {
                if ($w['id'] === $currentWs) {
                    $label = $w['name'];
                    break;
                }
            }
        }

        return [
            'staff' => $staff,
            'candidate' => $candidate,
            'hasPlatform' => $hasPlatform,
            'currentType' => $currentType,
            'currentWorkspaceId' => $currentWs,
            'currentLabel' => $label,
        ];
    }
}
