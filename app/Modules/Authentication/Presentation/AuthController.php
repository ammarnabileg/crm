<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Authentication\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Authentication\Application\Authenticator;
use HaHireAI\Modules\Installer\Application\Installer;
use HaHireAI\Modules\Users\Application\UserRegistrar;
use Throwable;

/** Register / login / logout (docs/USER_MODEL.md, docs/SECURITY_GUIDE.md). */
final class AuthController
{
    public function __construct(
        private readonly View $view,
        private readonly Authenticator $authenticator,
        private readonly UserRegistrar $registrar,
        private readonly AuthContext $auth,
        private readonly Session $session,
        private readonly Installer $installer,
    ) {
    }

    /** The site root: send visitors to setup (if needed) or into the app. */
    public function home(): Response
    {
        if (! $this->installer->isInstalled()) {
            return Response::redirect('/install');
        }

        return Response::redirect($this->auth->check() ? '/dashboard' : '/login');
    }

    public function showLogin(): Response
    {
        if (! $this->installer->isInstalled()) {
            return Response::redirect('/install');
        }

        if ($this->auth->check()) {
            return Response::redirect('/dashboard');
        }

        return Response::html($this->view->page('auth.login', [
            'status' => $this->session->pullFlash('status'),
            'error' => $this->session->pullFlash('error'),
        ], 'layouts.guest', ['title' => 'Sign in']));
    }

    public function login(Request $request): Response
    {
        if (! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return $this->backToLogin('Security check failed.');
        }

        $user = $this->authenticator->attempt(
            (string) $request->input('email', ''),
            (string) $request->input('password', ''),
        );

        if ($user === null) {
            return $this->backToLogin('Invalid email or password.');
        }

        $this->auth->login((string) $user['id']);

        return Response::redirect('/dashboard');
    }

    public function showRegister(): Response
    {
        if (! $this->installer->isInstalled()) {
            return Response::redirect('/install');
        }

        if ($this->auth->check()) {
            return Response::redirect('/dashboard');
        }

        return Response::html($this->view->page('auth.register', [
            'error' => $this->session->pullFlash('error'),
        ], 'layouts.guest', ['title' => 'Create your account']));
    }

    public function register(Request $request): Response
    {
        if (! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            $this->session->flash('error', 'Security check failed.');

            return Response::redirect('/register');
        }

        try {
            $userId = $this->registrar->register(
                (string) $request->input('name', ''),
                (string) $request->input('email', ''),
                (string) $request->input('password', ''),
            );
        } catch (Throwable $e) {
            $this->session->flash('error', $e->getMessage());

            return Response::redirect('/register');
        }

        $this->auth->login($userId);

        return Response::redirect('/dashboard');
    }

    public function logout(Request $request): Response
    {
        if ($this->session->verifyCsrf((string) $request->input('_csrf'))) {
            $this->auth->logout();
        }

        return Response::redirect('/login');
    }

    private function backToLogin(string $error): Response
    {
        $this->session->flash('error', $error);

        return Response::redirect('/login');
    }
}
