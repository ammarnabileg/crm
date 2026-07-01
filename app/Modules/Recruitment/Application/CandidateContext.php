<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Modules\Authentication\Application\AuthContext;

/**
 * The per-request candidate context: resolves which workspace the current user is
 * browsing *as a candidate* (User → Applications → Current Workspace → Candidate
 * Context). The mirror of WorkspaceContext, which resolves the *member* context.
 * A user can be staff in one workspace and a candidate in another at the same time.
 */
final class CandidateContext
{
    /** @var array<string, mixed>|null */
    private ?array $workspace = null;

    /** @var list<array<string, mixed>> */
    private array $workspaces = [];

    private bool $resolved = false;

    public function __construct(
        private readonly AuthContext $auth,
        private readonly CandidacyService $candidacy,
    ) {
    }

    /** True if an authenticated user is a candidate in at least one workspace. */
    public function resolve(): bool
    {
        $this->resolved = true;

        if (! $this->auth->check()) {
            return false;
        }

        $this->workspaces = $this->candidacy->workspacesForCandidate((string) $this->auth->id());
        if ($this->workspaces === []) {
            return false;
        }

        // Honor the user's current workspace if they're a candidate there;
        // otherwise default to their most recent candidate workspace.
        $currentId = $this->auth->currentWorkspaceId();
        $this->workspace = null;
        foreach ($this->workspaces as $w) {
            if ((string) $w['id'] === $currentId) {
                $this->workspace = $w;
                break;
            }
        }
        $this->workspace ??= $this->workspaces[0];
        $this->auth->setCurrentWorkspace((string) $this->workspace['id']);

        return true;
    }

    /** Force a specific workspace as the current candidate context (after a guard). */
    public function useWorkspace(string $workspaceId): bool
    {
        if (! $this->auth->check() || ! $this->candidacy->isCandidate($workspaceId, (string) $this->auth->id())) {
            return false;
        }
        $this->auth->setCurrentWorkspace($workspaceId);

        return $this->resolve();
    }

    /** @return array<string, mixed>|null */
    public function workspace(): ?array
    {
        return $this->workspace;
    }

    public function workspaceId(): ?string
    {
        return $this->workspace !== null ? (string) $this->workspace['id'] : null;
    }

    public function userId(): ?string
    {
        return $this->auth->id();
    }

    /** @return list<array<string, mixed>> all workspaces the user is a candidate in */
    public function workspaces(): array
    {
        return $this->workspaces;
    }
}
