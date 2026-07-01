<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Platform\Presentation;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Users\Application\PasswordHasher;
use HaHireAI\Modules\Users\Infrastructure\UserRepository;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/**
 * Self-service profile for the signed-in user: display name, email, and password.
 * Reached from the header user menu. Account-level (works in any workspace), so
 * it bypasses the workspace gate.
 */
final class ProfileController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/workspaces/create');
        }

        return $this->shell->render($this->context, 'account.profile', [
            'profile' => $this->auth->user(),
            'status' => $this->session->pullFlash('status'),
            'error' => $this->session->pullFlash('error'),
        ], ['bypassGate' => true]);
    }

    public function update(Request $request): Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>419</h1><p>Security check failed.</p>', 419);
        }

        $id = (string) $this->auth->id();
        $user = $this->users->find($id);
        if ($user === null) {
            return Response::redirect('/login');
        }

        $name = trim((string) $request->input('name', ''));
        $email = strtolower(trim((string) $request->input('email', '')));
        if ($name === '' || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->session->flash('error', 'A valid name and email are required.');

            return Response::redirect('/account/profile');
        }

        // Email change must stay unique.
        if ($email !== strtolower((string) $user['email'])) {
            $existing = $this->users->findByEmail($email);
            if ($existing !== null && (string) $existing['id'] !== $id) {
                $this->session->flash('error', 'That email is already in use.');

                return Response::redirect('/account/profile');
            }
            $this->users->updateEmail($id, $email);
        }

        if ($name !== (string) $user['name']) {
            $this->users->updateName($id, $name);
        }

        // Optional password change: requires the current password to match.
        $new = (string) $request->input('new_password', '');
        if ($new !== '') {
            if (! $this->hasher->verify((string) $request->input('current_password', ''), (string) $user['password_hash'])) {
                $this->session->flash('error', 'Your current password is incorrect.');

                return Response::redirect('/account/profile');
            }
            if (strlen($new) < 8) {
                $this->session->flash('error', 'The new password must be at least 8 characters.');

                return Response::redirect('/account/profile');
            }
            if ($new !== (string) $request->input('new_password_confirmation', '')) {
                $this->session->flash('error', 'The new password and confirmation do not match.');

                return Response::redirect('/account/profile');
            }
            $this->users->updatePassword($id, $this->hasher->hash($new));
        }

        $this->audit->record('account.profile.updated', [
            'actor_user_id' => $id,
            'entity_type' => 'user',
            'entity_id' => $id,
            'changes' => ['name' => $name, 'email' => $email, 'password' => $new !== ''],
        ]);
        $this->session->flash('status', 'Your profile has been updated.');

        return Response::redirect('/account/profile');
    }
}
