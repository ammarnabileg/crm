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
