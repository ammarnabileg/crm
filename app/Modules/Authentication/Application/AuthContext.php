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
}
