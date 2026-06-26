<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Core\Hash;
use App\Core\Session;
use App\Models\User;

/**
 * Session-backed authentication. There is exactly one login for the whole
 * platform; what a user can do afterwards is determined entirely by RBAC, not
 * by which "type" of account they have.
 */
final class AuthManager
{
    private ?User $user = null;
    private bool $resolved = false;

    public function __construct(private readonly Session $session)
    {
    }

    private function sessionKey(): string
    {
        return (string) config('auth.session_key', 'auth_user_id');
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function id(): ?int
    {
        $id = $this->session->get($this->sessionKey());

        return is_numeric($id) ? (int) $id : null;
    }

    public function user(): ?User
    {
        if ($this->resolved) {
            return $this->user;
        }
        $this->resolved = true;

        $id = $this->id();
        if ($id === null) {
            return $this->user = null;
        }

        $user = User::find($id);

        // Defend against suspended/deleted accounts holding a live session.
        if ($user === null || ! $user->isActive()) {
            $this->logout();

            return $this->user = null;
        }

        return $this->user = $user;
    }

    /**
     * Verify credentials without logging in (used for re-auth flows).
     */
    public function validate(string $email, string $password): ?User
    {
        $row = User::withoutTenantScope()->where('email', '=', mb_strtolower(trim($email)))->first();
        if ($row === null) {
            // Equalise timing whether or not the email exists.
            Hash::verify($password, '$2y$12$' . str_repeat('.', 53));

            return null;
        }

        $user = User::hydrate($row);
        if (! Hash::verify($password, (string) $row['password'])) {
            return null;
        }

        return $user;
    }

    public function attempt(string $email, string $password): ?User
    {
        $user = $this->validate($email, $password);
        if ($user === null || ! $user->isActive()) {
            return null;
        }

        $this->login($user);

        // Transparently upgrade legacy hashes on successful login.
        if (Hash::needsRehash((string) $user->getAttribute('password'))) {
            User::withoutTenantScope()->where('id', '=', $user->getKey())
                ->update(['password' => Hash::make($password), 'updated_at' => now()]);
        }

        return $user;
    }

    public function login(User $user): void
    {
        $this->session->regenerate();
        $this->session->put($this->sessionKey(), (int) $user->getKey());
        $this->user = $user;
        $this->resolved = true;

        $user->recordLogin(request()->ip());
    }

    public function loginById(int $id): bool
    {
        $user = User::find($id);
        if ($user === null) {
            return false;
        }

        $this->login($user);

        return true;
    }

    public function logout(): void
    {
        $this->session->forget($this->sessionKey());
        $this->session->forget((string) config('auth.tenant_key', 'active_company_id'));
        $this->session->invalidate();
        $this->user = null;
        $this->resolved = true;
    }
}
