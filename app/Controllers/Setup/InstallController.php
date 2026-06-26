<?php

declare(strict_types=1);

namespace App\Controllers\Setup;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Install\InstallManager;
use Throwable;

/**
 * The web installer. Renders the wizard and exposes one JSON endpoint per step
 * so the front-end can drive installation with a live console and resume from
 * the last completed step on failure. No CLI, ever.
 */
final class InstallController extends Controller
{
    private function manager(): InstallManager
    {
        return new InstallManager(app('path.base'));
    }

    public function index(Request $request): Response
    {
        $manager = $this->manager();

        // Already installed? Send people to the app rather than re-running setup.
        if ($manager->isInstalled()) {
            return $this->redirect(url('login'));
        }

        return $this->view('setup.install', [
            'state'      => $manager->state(),
            'nextStep'   => $manager->nextStep(),
            'steps'      => InstallManager::STEPS,
            'phpVersion' => PHP_VERSION,
            'defaultUrl' => $this->guessAppUrl($request),
        ]);
    }

    public function requirements(Request $request): Response
    {
        return $this->json($this->manager()->checkRequirements());
    }

    public function database(Request $request): Response
    {
        return $this->guard(function () use ($request) {
            $this->manager()->configureDatabase([
                'host'     => (string) $request->input('db_host', '127.0.0.1'),
                'port'     => (string) $request->input('db_port', '3306'),
                'database' => (string) $request->input('db_database', ''),
                'username' => (string) $request->input('db_username', ''),
                'password' => (string) $request->input('db_password', ''),
            ]);

            return ['message' => 'Database connection verified and database ready.'];
        });
    }

    public function migrate(Request $request): Response
    {
        return $this->guard(function () {
            $log = [];
            $result = $this->manager()->runMigrations(function (string $name, bool $ok, ?string $error) use (&$log): void {
                $log[] = ['name' => $name, 'ok' => $ok, 'error' => $error];
            });

            if ($result['failed'] !== null) {
                throw new \RuntimeException('Migration failed — ' . $result['failed']);
            }

            $count = count($result['ran']);

            return [
                'message' => $count === 0 ? 'Schema already up to date.' : "Created database schema ({$count} migrations).",
                'log'     => $log,
            ];
        });
    }

    public function seed(Request $request): Response
    {
        return $this->guard(function () {
            $this->manager()->runSeeders();

            return ['message' => 'Seeded permissions, roles and the default plan.'];
        });
    }

    public function admin(Request $request): Response
    {
        return $this->guard(function () use ($request) {
            $this->manager()->createAdmin([
                'name'     => (string) $request->input('admin_name', ''),
                'email'    => (string) $request->input('admin_email', ''),
                'password' => (string) $request->input('admin_password', ''),
            ]);

            return ['message' => 'Administrator account created.'];
        });
    }

    public function finalize(Request $request): Response
    {
        return $this->guard(function () use ($request) {
            $this->manager()->finalize([
                'app_name' => (string) $request->input('app_name', 'HalaOps'),
                'app_url'  => (string) $request->input('app_url', $this->guessAppUrl($request)),
            ]);

            return [
                'message'  => 'Installation complete.',
                'redirect' => url('login'),
            ];
        });
    }

    /**
     * Run a step and normalise success/failure into the JSON envelope the
     * installer console understands.
     */
    private function guard(callable $callback): Response
    {
        // Refuse to mutate anything once the lock is in place.
        if ($this->manager()->isInstalled()) {
            return $this->json(['ok' => false, 'message' => 'The application is already installed.'], 409);
        }

        try {
            $payload = $callback();

            return $this->json(['ok' => true] + (array) $payload);
        } catch (Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function guessAppUrl(Request $request): string
    {
        $scheme = $request->isSecure() ? 'https' : 'http';
        $host = (string) $request->server('HTTP_HOST', 'localhost');

        return $scheme . '://' . $host;
    }
}
