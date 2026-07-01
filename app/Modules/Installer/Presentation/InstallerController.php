<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Installer\Presentation;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Core\View\View;
use HaHireAI\Modules\Installer\Application\Installer;
use Throwable;

/**
 * The zero-touch browser installer (docs/INSTALLATION_FLOW.md): the buyer enters
 * their database credentials and the first owner account — nothing else — and the
 * page runs the whole install itself, with a live log and a safe, allow-listed
 * setup console. No terminal, no Composer, no manual SQL.
 */
final class InstallerController
{
    public function __construct(
        private readonly View $view,
        private readonly Installer $installer,
        private readonly Session $session,
        private readonly Container $container,
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
            'commands' => $this->installer->consoleCommands(),
            'consoleOutput' => $this->session->pullFlash('console_output'),
            'db' => [
                'host' => (string) env('DB_HOST', '127.0.0.1'),
                'port' => (string) env('DB_PORT', '3306'),
                'database' => (string) env('DB_DATABASE', 'hahireai'),
                'username' => (string) env('DB_USERNAME', ''),
            ],
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

        $db = [
            'host' => trim((string) $request->input('db_host', '127.0.0.1')),
            'port' => (int) $request->input('db_port', 3306),
            'database' => trim((string) $request->input('db_database', '')),
            'username' => trim((string) $request->input('db_username', '')),
            'password' => (string) $request->input('db_password', ''),
        ];
        $owner = [
            'name' => (string) $request->input('name', ''),
            'email' => (string) $request->input('email', ''),
            'password' => (string) $request->input('password', ''),
        ];

        $log = [];
        try {
            $this->installer->testDatabase($db);
            $this->installer->writeDatabaseConfig($db);

            // Point the shared connection at the buyer's database for the rest of
            // this request. The migration runner, schema builder, permission seeder
            // and user registrar all hold this same singleton instance, so they all
            // switch to it at once — rebinding the container key would leave those
            // already-built singletons stranded on the default (pre-.env) config.
            $this->container->make(Connection::class)->reconfigure($db + ['charset' => 'utf8mb4']);

            $this->installer->install($owner, static function (string $line) use (&$log): void {
                $log[] = $line;
            });
        } catch (Throwable $e) {
            $this->session->flash('error', $e->getMessage());

            return Response::redirect('/install');
        }

        return Response::html($this->view->page('install.done', [
            'log' => $log,
        ], 'layouts.guest', ['title' => 'Installation complete']));
    }

    /** Run one allow-listed setup command during the install window (pre-lock). */
    public function console(Request $request): Response
    {
        if ($this->installer->isInstalled()) {
            return Response::redirect('/login');
        }
        if (! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            $this->session->flash('error', 'Security check failed.');

            return Response::redirect('/install');
        }

        $command = (string) $request->input('command', '');
        try {
            $this->session->flash('console_output', '$ ' . $command . "\n" . $this->installer->runConsole($command));
        } catch (Throwable $e) {
            $this->session->flash('console_output', '$ ' . $command . "\n" . $e->getMessage());
        }

        return Response::redirect('/install');
    }
}
