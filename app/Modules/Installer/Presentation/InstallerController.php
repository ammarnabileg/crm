<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Installer\Presentation;

use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Installer\Application\Installer;
use Throwable;

/** The zero-touch browser installer (docs/INSTALLATION_FLOW.md). */
final class InstallerController
{
    public function __construct(
        private readonly View $view,
        private readonly Installer $installer,
        private readonly Session $session,
    ) {
    }

    public function show(): Response
    {
        if ($this->installer->isInstalled()) {
            return Response::redirect('/login');
        }

        return Response::html($this->view->page('install.wizard', [
            'requirements' => $this->installer->requirements(),
            'satisfied' => $this->installer->requirementsSatisfied(),
            'error' => $this->session->pullFlash('error'),
        ], 'layouts.guest', ['title' => 'Install HaHireAI']));
    }

    public function run(Request $request): Response
    {
        if ($this->installer->isInstalled()) {
            return Response::redirect('/login');
        }

        if (! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            $this->session->flash('error', 'Security check failed. Please try again.');

            return Response::redirect('/install');
        }

        try {
            $this->installer->install([
                'name' => (string) $request->input('name', ''),
                'email' => (string) $request->input('email', ''),
                'password' => (string) $request->input('password', ''),
            ]);
        } catch (Throwable $e) {
            $this->session->flash('error', $e->getMessage());

            return Response::redirect('/install');
        }

        $this->session->flash('status', 'Installation complete. Please sign in as the System Owner.');

        return Response::redirect('/login');
    }
}
