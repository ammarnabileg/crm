<?php

declare(strict_types=1);

namespace App\Controllers\Setup;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Install\InstallManager;
use Throwable;

/**
 * The web installer (Setup & Installer Bible). Renders the wizard and exposes one
 * JSON endpoint per operation so the front-end drives installation with a live
 * console + real progress bar, resumes from the last completed step on failure,
 * and never asks the user to touch a terminal.
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
        if ($manager->isInstalled()) {
            return $this->redirect(url('login'));
        }

        return $this->view('setup.install', [
            'state'      => $manager->state(),
            'nextStep'   => $manager->nextStep(),
            'steps'      => InstallManager::STEPS,
            'progress'   => $manager->progress(),
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

    public function environment(Request $request): Response
    {
        return $this->guard(function () {
            $this->manager()->generateEnvironment();

            return ['message' => 'Generated the application encryption key.'];
        });
    }

    public function storage(Request $request): Response
    {
        return $this->guard(function () {
            $results = $this->manager()->createStorage();
            $made = count(array_filter($results, static fn ($r) => $r['ok']));

            return ['message' => "Storage folders ready ({$made}/" . count($results) . ').', 'log' => array_map(
                static fn ($r) => ['name' => $r['path'], 'ok' => $r['ok'], 'error' => $r['ok'] ? null : 'not created'],
                $results
            )];
        });
    }

    public function permissions(Request $request): Response
    {
        return $this->guard(function () {
            $res = $this->manager()->checkPermissions();
            if (! $res['ok']) {
                throw new \RuntimeException('Some storage folders are not writable. Click "Auto-Fix permissions" and try again.');
            }

            return ['message' => 'All storage folders are writable.'];
        });
    }

    public function fixPermissions(Request $request): Response
    {
        return $this->guard(function () {
            $res = $this->manager()->fixPermissions();

            return [
                'ok_fixed' => $res['ok'],
                'message'  => $res['ok'] ? 'Permissions repaired — all folders are writable.' : 'Some folders are still not writable; please set them to 775 in your hosting file manager.',
                'log'      => array_map(static fn ($d) => ['name' => $d['path'], 'ok' => $d['writable'], 'error' => $d['writable'] ? null : 'not writable'], $res['dirs']),
            ];
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

            return ['message' => 'Seeded reference data, lookups, modules, permissions, roles and the default plan.'];
        });
    }

    public function mail(Request $request): Response
    {
        return $this->guard(function () use ($request) {
            if ($request->input('skip')) {
                $this->manager()->skipMail();

                return ['message' => 'Mail step skipped — you can configure it later from the dashboard.'];
            }

            $this->manager()->configureMail([
                'enabled'      => (bool) $request->input('mail_enabled', false),
                'from_address' => (string) $request->input('mail_from_address', 'no-reply@halaops.local'),
                'from_name'    => (string) $request->input('mail_from_name', 'HalaOps'),
            ]);

            return ['message' => 'Mail settings saved.'];
        });
    }

    public function testMail(Request $request): Response
    {
        return $this->guard(function () use ($request) {
            return $this->manager()->sendTestEmail((string) $request->input('test_email', ''), [
                'enabled'      => (bool) $request->input('mail_enabled', false),
                'from_address' => (string) $request->input('mail_from_address', 'no-reply@halaops.local'),
                'from_name'    => (string) $request->input('mail_from_name', 'HalaOps'),
            ]);
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

    public function health(Request $request): Response
    {
        return $this->json($this->manager()->healthCheck());
    }

    public function finalize(Request $request): Response
    {
        return $this->guard(function () use ($request) {
            $this->manager()->finalize([
                'app_name' => (string) $request->input('app_name', 'HalaOps'),
                'app_url'  => (string) $request->input('app_url', $this->guessAppUrl($request)),
            ]);

            return [
                'message'  => 'Installation complete. Redirecting you to sign in...',
                'redirect' => url('login'),
            ];
        });
    }

    private function guard(callable $callback): Response
    {
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
