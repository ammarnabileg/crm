<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Authentication\Application;

use HaHireAI\Core\Contracts\UserDirectory;
use HaHireAI\Core\Http\Session;

/** The current authenticated user, backed by the session. */
final class AuthContext
{
    public function __construct(
        private readonly Session $session,
        private readonly UserDirectory $users,
    ) {
    }

    public function login(string $userId): void
    {
        $this->session->regenerate();
        $this->session->put('auth_user_id', $userId);
    }

    public function logout(): void
    {
        $this->session->forget('auth_user_id');
        $this->session->forget('workspace_id');
        $this->session->forget('context_type');
        $this->session->regenerate();
    }

    public function check(): bool
    {
        return $this->session->has('auth_user_id');
    }

    public function id(): ?string
    {
        $id = $this->session->get('auth_user_id');

        return is_string($id) ? $id : null;
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        $id = $this->id();

        return $id === null ? null : $this->users->find($id);
    }

    public function currentWorkspaceId(): ?string
    {
        $id = $this->session->get('workspace_id');

        return is_string($id) ? $id : null;
    }

    public function setCurrentWorkspace(string $workspaceId): void
    {
        $this->session->put('workspace_id', $workspaceId);
    }

    /**
     * Which context the user is acting in for the shared, context-aware surface
     * (/dashboard and the workspace-scoped pages): 'staff' (a workspace member),
     * 'candidate' (an applicant in a workspace), or 'platform' (the HaHireAI
     * owner panel). Defaults to 'staff'. The active *workspace* is separate
     * (currentWorkspaceId); together they decide what each page renders.
     */
    public function contextType(): string
    {
        $t = $this->session->get('context_type');

        return is_string($t) && in_array($t, ['staff', 'candidate', 'platform'], true) ? $t : 'staff';
    }

    public function setContextType(string $type): void
    {
        $this->session->put('context_type', $type);
    }
}
