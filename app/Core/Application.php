<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\ValidationException;
use App\Services\Auth\AuthManager;
use App\Services\Rbac\AccessControl;
use App\Services\Tenancy\TenantManager;
use ErrorException;
use Throwable;

/**
 * Application kernel: boots configuration, wires the service container and runs
 * the HTTP request lifecycle (routing, middleware, error handling).
 *
 * It is deliberately tolerant of the "not yet installed" state — only the
 * config/env loading and a handful of services are needed before the database
 * exists, so the web installer can run on a fresh upload with no .env present.
 */
final class Application
{
    public const VERSION = '1.0.0';

    private Container $container;
    private bool $booted = false;

    public function __construct(private readonly string $basePath)
    {
        $this->container = Container::getInstance();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->registerErrorHandling();

        Env::load($this->basePath . '/.env');

        $this->registerPaths();
        $this->registerCoreServices();

        date_default_timezone_set((string) config('app.timezone', 'UTC'));
        mb_internal_encoding('UTF-8');

        $this->registerEventsAndPolicies();

        $this->booted = true;
    }

    /**
     * Register domain event listeners and authorization policies. Listeners keep
     * side effects (audit, etc.) out of the use-case code; policies add
     * context-aware authorization on top of permissions (docs/47 EAS-6/EAS-11).
     */
    private function registerEventsAndPolicies(): void
    {
        $events = app('events');
        $events->listen(\App\Events\UserRegistered::class, fn ($e) => app(\App\Listeners\RecordUserRegistered::class)($e));
        $events->listen(\App\Events\CompanyCreated::class, fn ($e) => app(\App\Listeners\RecordCompanyCreated::class)($e));

        $access = app('access');
        $access->define('company.view', fn ($u, $c) => app(\App\Domain\Policies\CompanyPolicy::class)->view($u, $c));
        $access->define('company.update', fn ($u, $c) => app(\App\Domain\Policies\CompanyPolicy::class)->update($u, $c));
        $access->define('company.delete', fn ($u, $c) => app(\App\Domain\Policies\CompanyPolicy::class)->delete($u, $c));
    }

    private function registerPaths(): void
    {
        $this->container->instance('path.base', $this->basePath);
        $this->container->instance('path.storage', $this->basePath . '/storage');
        $this->container->instance('path.config', $this->basePath . '/config');
        $this->container->instance('path.resources', $this->basePath . '/resources');
    }

    private function registerCoreServices(): void
    {
        $c = $this->container;
        $base = $this->basePath;

        $c->singleton('config', fn () => new Config($base . '/config'));

        $c->singleton('log', fn () => new Logger($base . '/storage/logs'));

        $c->singleton('encrypter', function () {
            $key = (string) config('app.key', '');
            if ($key === '') {
                // Pre-install fallback so the installer can run without a key.
                $key = 'base64:' . base64_encode(str_repeat('0', 32));
            }
            return new Encrypter($key);
        });

        $c->singleton('session', function () use ($base) {
            return new Session(
                savePath: $base . '/storage/sessions',
                cookieName: (string) config('session.cookie', 'halaops_session'),
                secure: (bool) config('session.secure', false),
                lifetime: (int) config('session.lifetime', 7200),
            );
        });

        $c->singleton('translator', function () use ($base) {
            return new Translator(
                locale: (string) config('app.locale', 'en'),
                fallback: (string) config('app.fallback_locale', 'en'),
                langPath: $base . '/resources/lang',
            );
        });

        $c->singleton('view', function () use ($base) {
            $view = new View($base . '/resources/views');
            $this->shareViewGlobals($view);

            return $view;
        });

        $c->singleton('db', function () {
            $default = config('database.default', 'mysql');

            return new Database(config("database.connections.{$default}", []));
        });

        $c->singleton('router', function () use ($c) {
            $router = new Router($c);
            $router->setAliases(require $this->basePath . '/config/middleware.php');

            return $router;
        });

        $c->singleton('tenant', fn () => new TenantManager());
        $c->singleton('auth', fn () => new AuthManager(app('session')));
        $c->singleton('access', fn () => new AccessControl(app('auth'), app('tenant')));

        $c->singleton('mailer', fn () => new Mailer(
            fromAddress: (string) config('mail.from_address', 'no-reply@halaops.local'),
            fromName: (string) config('mail.from_name', 'HalaOps'),
            logPath: $base . '/storage/logs',
        ));

        // --- Cache layer (swappable behind the CacheStore contract) ---------
        $c->singleton(\App\Contracts\Cache\CacheStore::class, fn () => new \App\Infrastructure\Cache\FileStore($base . '/storage/cache'));
        $c->singleton('cache', fn () => app(\App\Contracts\Cache\CacheStore::class));

        // --- Class-name aliases so autowiring resolves core services by type.
        // The kernel registers these under short keys; map the concrete types to
        // them so constructor injection (docs/47 EAS-2) works everywhere.
        $c->bind(Config::class, fn () => app('config'));
        $c->bind(Logger::class, fn () => app('log'));
        $c->bind(Encrypter::class, fn () => app('encrypter'));
        $c->bind(Session::class, fn () => app('session'));
        $c->bind(Translator::class, fn () => app('translator'));
        $c->bind(Database::class, fn () => app('db'));
        $c->bind(Router::class, fn () => app('router'));
        $c->bind(Mailer::class, fn () => app('mailer'));
        $c->bind(TenantManager::class, fn () => app('tenant'));
        $c->bind(AuthManager::class, fn () => app('auth'));
        $c->bind(AccessControl::class, fn () => app('access'));

        // --- Settings + feature flags (autowired from the above) ------------
        $c->singleton(\App\Services\Settings\SettingsManager::class);
        $c->singleton(\App\Services\Settings\FeatureFlags::class);
        $c->singleton('settings', fn () => app(\App\Services\Settings\SettingsManager::class));
        $c->singleton('features', fn () => app(\App\Services\Settings\FeatureFlags::class));

        // --- Repositories (all persistence flows through these; EAS-3) -------
        $c->bind(\App\Contracts\Repositories\UserRepositoryInterface::class, \App\Repositories\UserRepository::class);
        $c->bind(\App\Contracts\Repositories\CompanyRepositoryInterface::class, \App\Repositories\CompanyRepository::class);

        // --- Events + audit (side effects via listeners; EAS-6/EAS-7) -------
        $c->singleton(\App\Contracts\Events\EventDispatcherInterface::class, \App\Infrastructure\Events\EventDispatcher::class);
        $c->singleton('events', fn () => app(\App\Contracts\Events\EventDispatcherInterface::class));
        $c->singleton(\App\Contracts\Audit\AuditLogger::class, \App\Services\Audit\DatabaseAuditLogger::class);
        $c->singleton('audit', fn () => app(\App\Contracts\Audit\AuditLogger::class));
    }

    private function shareViewGlobals(View $view): void
    {
        $view->share('view', $view);
        $view->share('appName', (string) config('app.name', 'HalaOps'));
    }

    public function handle(Request $request): Response
    {
        $this->boot();

        $this->container->instance('request', $request);
        session()->start();

        // Localisation: honour an explicit ?lang or a stored session preference.
        $this->resolveLocale($request);

        try {
            if (! $this->isInstalled()) {
                return $this->handleNotInstalled($request);
            }

            $this->bootTenantContext();
            $this->loadRoutes();

            return app('router')->dispatch($request);
        } catch (ValidationException $e) {
            return $this->renderValidationException($request, $e);
        } catch (HttpException $e) {
            return $this->renderHttpException($request, $e);
        } catch (Throwable $e) {
            return $this->renderException($request, $e);
        }
    }

    private function resolveLocale(Request $request): void
    {
        $translator = app('translator');
        $supported = (array) config('app.supported_locales', ['en', 'ar']);

        $lang = $request->query('lang');
        if (is_string($lang) && in_array($lang, $supported, true)) {
            session()->put('locale', $lang);
        }

        $locale = session()->get('locale');
        if (is_string($locale) && in_array($locale, $supported, true)) {
            $translator->setLocale($locale);
        }
    }

    /**
     * Once authenticated, establish the active tenant so model scoping is in
     * force for the rest of the request, and honour the user's preferred locale
     * when the visitor has not explicitly chosen one.
     */
    private function bootTenantContext(): void
    {
        if (! auth()->check()) {
            return;
        }

        $user = auth()->user();
        tenant()->bootFor($user);

        if (! session()->has('locale')) {
            $userLocale = (string) $user->getAttribute('locale');
            $supported = (array) config('app.supported_locales', ['en', 'ar']);
            if (in_array($userLocale, $supported, true)) {
                app('translator')->setLocale($userLocale);
            }
        }
    }

    private function loadRoutes(): void
    {
        $router = app('router');
        require $this->basePath . '/routes/web.php';
    }

    public function isInstalled(): bool
    {
        return is_file($this->basePath . '/storage/framework/installed')
            && is_file($this->basePath . '/.env');
    }

    private function handleNotInstalled(Request $request): Response
    {
        $path = $request->path();

        // Allow the installer and its assets through; redirect everything else.
        if (str_starts_with($path, '/install') || str_starts_with($path, '/assets')) {
            $this->loadRoutes();

            return app('router')->dispatch($request);
        }

        return Response::redirect(url('install'));
    }

    // --- Error handling ----------------------------------------------------

    private function registerErrorHandling(): void
    {
        error_reporting(E_ALL);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (! (error_reporting() & $severity)) {
                return false; // respect the @ operator
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (Throwable $e): void {
            try {
                $this->renderException(request(), $e)->send();
            } catch (Throwable) {
                http_response_code(500);
                echo 'A fatal error occurred.';
            }
        });
    }

    private function renderValidationException(Request $request, ValidationException $e): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['message' => $e->getMessage(), 'errors' => $e->errors], 422);
        }

        session()->flash('errors', $e->errors);
        session()->flashInput($e->input);

        return back();
    }

    private function renderHttpException(Request $request, HttpException $e): Response
    {
        $status = $e->getStatusCode();

        if ($request->wantsJson()) {
            return Response::json(['message' => $e->getMessage() ?: $this->statusText($status)], $status);
        }

        $template = 'errors.' . $status;
        $view = app('view');
        if (! $this->viewExists($template)) {
            $template = 'errors.generic';
        }

        return Response::make(
            $view->render($template, [
                'status'  => $status,
                'message' => $e->getMessage() ?: $this->statusText($status),
            ]),
            $status
        );
    }

    private function renderException(Request $request, Throwable $e): Response
    {
        logger()->error($e->getMessage(), [
            'exception' => $e::class,
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
        ]);

        $debug = (bool) config('app.debug', false);

        if ($request->wantsJson()) {
            $payload = ['message' => $debug ? $e->getMessage() : 'Server Error.'];
            if ($debug) {
                $payload['exception'] = $e::class;
                $payload['file'] = $e->getFile() . ':' . $e->getLine();
            }

            return Response::json($payload, 500);
        }

        if ($debug) {
            return Response::make($this->renderDebugPage($e), 500);
        }

        return Response::make(
            app('view')->render('errors.500', ['status' => 500, 'message' => 'Server Error']),
            500
        );
    }

    private function renderDebugPage(Throwable $e): string
    {
        $title = e($e::class);
        $message = e($e->getMessage());
        $location = e($e->getFile() . ':' . $e->getLine());
        $trace = e($e->getTraceAsString());

        return <<<HTML
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error</title>
<style>body{font-family:ui-monospace,monospace;background:#0f172a;color:#e2e8f0;padding:2rem;line-height:1.6}
h1{color:#f87171}.box{background:#1e293b;padding:1rem 1.5rem;border-radius:.5rem;margin:1rem 0;overflow:auto}
.loc{color:#fbbf24}</style></head><body>
<h1>{$title}</h1><div class="box">{$message}</div>
<div class="box"><span class="loc">{$location}</span></div>
<div class="box"><pre>{$trace}</pre></div></body></html>
HTML;
    }

    private function viewExists(string $template): bool
    {
        $file = $this->basePath . '/resources/views/' . str_replace('.', '/', $template) . '.php';

        return is_file($file);
    }

    private function statusText(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            419 => 'Page Expired',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Server Error',
            503 => 'Service Unavailable',
            default => 'Error',
        };
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }
}
