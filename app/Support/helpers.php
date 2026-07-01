<?php

declare(strict_types=1);

use HaHireAI\Core\Config\Environment;
use HaHireAI\Core\Config\Repository;
use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Kernel;

/*
 * Global helpers. These exist for the BOOTSTRAP and CONFIG/VIEW layers only.
 * Business logic MUST use dependency injection, never these helpers
 * (see docs/CODING_STANDARD.md — no service locator in business logic).
 */

if (! function_exists('app')) {
    /** Resolve from the container, or get the container itself when no id is given. */
    function app(?string $id = null): object
    {
        $container = Kernel::instance()->container();

        return $id === null ? $container : $container->make($id);
    }
}

if (! function_exists('env')) {
    /** Read an environment value (config layer only). */
    function env(string $key, mixed $default = null): mixed
    {
        /** @var Environment $environment */
        $environment = app(Environment::class);

        return $environment->get($key, $default);
    }
}

if (! function_exists('config')) {
    /** Read configuration via dot notation (config/view layer only). */
    function config(string $key, mixed $default = null): mixed
    {
        /** @var Repository $repository */
        $repository = app(Repository::class);

        return $repository->get($key, $default);
    }
}

if (! function_exists('base_path')) {
    /** Absolute path within the project root. */
    function base_path(string $path = ''): string
    {
        return Kernel::instance()->basePath($path);
    }
}

if (! function_exists('storage_path')) {
    /** Absolute path within the storage directory. */
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }
}

if (! function_exists('e')) {
    /** Escape a value for safe HTML output (XSS protection — always escape on render). */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('time_ago')) {
    /** Human-friendly relative time for a stored UTC datetime ("3m ago", "2d ago"). */
    function time_ago(mixed $datetime): string
    {
        $value = trim((string) ($datetime ?? ''));
        if ($value === '') {
            return 'never';
        }

        $then = strtotime($value . ' UTC');
        if ($then === false) {
            return 'never';
        }

        $diff = time() - $then;
        if ($diff < 0) {
            $diff = 0;
        }
        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            return (int) ($diff / 60) . 'm ago';
        }
        if ($diff < 86400) {
            return (int) ($diff / 3600) . 'h ago';
        }
        if ($diff < 2592000) {
            return (int) ($diff / 86400) . 'd ago';
        }

        return gmdate('M j, Y', $then);
    }
}

if (! function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return app(\HaHireAI\Core\Http\Session::class)->csrfToken();
    }
}

if (! function_exists('csrf_field')) {
    /** A hidden CSRF input for forms (state-changing requests must include it). */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}
