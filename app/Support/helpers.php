<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Container;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\Auth\AuthManager;
use App\Services\Rbac\AccessControl;
use App\Services\Tenancy\TenantManager;

if (! function_exists('app')) {
    /**
     * Resolve a service from the container, or the container itself.
     */
    function app(?string $key = null): mixed
    {
        $container = Container::getInstance();

        return $key === null ? $container : $container->make($key);
    }
}

if (! function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        /** @var Config $config */
        $config = app('config');

        if ($key === null) {
            return $config;
        }

        return $config->get($key, $default);
    }
}

if (! function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (! function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $base = app('path.base');

        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}

if (! function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path === '' ? '' : '/' . ltrim($path, '/')));
    }
}

if (! function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return base_path('config' . ($path === '' ? '' : '/' . ltrim($path, '/')));
    }
}

if (! function_exists('database_path')) {
    function database_path(string $path = ''): string
    {
        return base_path('database' . ($path === '' ? '' : '/' . ltrim($path, '/')));
    }
}

if (! function_exists('resource_path')) {
    function resource_path(string $path = ''): string
    {
        return base_path('resources' . ($path === '' ? '' : '/' . ltrim($path, '/')));
    }
}

if (! function_exists('session')) {
    function session(): Session
    {
        return app('session');
    }
}

if (! function_exists('request')) {
    function request(): Request
    {
        return app('request');
    }
}

if (! function_exists('auth')) {
    function auth(): AuthManager
    {
        return app('auth');
    }
}

if (! function_exists('tenant')) {
    function tenant(): TenantManager
    {
        return app('tenant');
    }
}

if (! function_exists('access')) {
    function access(): AccessControl
    {
        return app('access');
    }
}

if (! function_exists('cache')) {
    function cache(): \App\Contracts\Cache\CacheStore
    {
        return app('cache');
    }
}

if (! function_exists('settings')) {
    function settings(): \App\Services\Settings\SettingsManager
    {
        return app('settings');
    }
}

if (! function_exists('feature')) {
    /**
     * Check whether a feature flag is enabled for the current tenant.
     */
    function feature(string $name): bool
    {
        return app('features')->enabled($name);
    }
}

if (! function_exists('can')) {
    /**
     * Check the current user against a permission (optionally for a model).
     */
    function can(string $permission, mixed $context = null): bool
    {
        return access()->allows($permission, $context);
    }
}

if (! function_exists('view')) {
    function view(string $template, array $data = [], int $status = 200): Response
    {
        /** @var View $view */
        $view = app('view');

        return Response::make($view->render($template, $data), $status);
    }
}

if (! function_exists('render')) {
    function render(string $template, array $data = []): string
    {
        return app('view')->render($template, $data);
    }
}

if (! function_exists('redirect')) {
    function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }
}

if (! function_exists('back')) {
    function back(int $status = 302): Response
    {
        $referer = request()->header('referer');
        $target = is_string($referer) && $referer !== '' ? $referer : url('/');

        return Response::redirect($target, $status);
    }
}

if (! function_exists('json_response')) {
    function json_response(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }
}

if (! function_exists('base_url')) {
    function base_url(): string
    {
        $configured = config('app.url');
        if (is_string($configured) && $configured !== '') {
            return rtrim($configured, '/');
        }

        $scheme = request()->isSecure() ? 'https' : 'http';
        $host = request()->server('HTTP_HOST', 'localhost');

        return $scheme . '://' . $host;
    }
}

if (! function_exists('url')) {
    function url(string $path = '/'): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim(base_url(), '/') . '/' . ltrim($path, '/');
    }
}

if (! function_exists('asset')) {
    function asset(string $path): string
    {
        $version = config('app.asset_version', '1');

        return url('assets/' . ltrim($path, '/')) . '?v=' . $version;
    }
}

if (! function_exists('route_to')) {
    function route_to(string $name, array $params = []): string
    {
        return app('router')->url($name, $params);
    }
}

if (! function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('old')) {
    function old(string $key, mixed $default = null): mixed
    {
        return session()->old($key, $default);
    }
}

if (! function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return session()->token();
    }
}

if (! function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (! function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (! function_exists('__')) {
    function __(string $key, array $replace = []): string
    {
        return app('translator')->get($key, $replace);
    }
}

if (! function_exists('locale')) {
    function locale(): string
    {
        return app('translator')->locale();
    }
}

if (! function_exists('is_rtl')) {
    function is_rtl(): bool
    {
        return app('translator')->isRtl();
    }
}

if (! function_exists('logger')) {
    function logger(): \App\Core\Logger
    {
        return app('log');
    }
}

if (! function_exists('now')) {
    function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (! function_exists('str_random')) {
    function str_random(int $length = 32): string
    {
        return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
    }
}

if (! function_exists('slugify')) {
    function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';

        return trim($value, '-') ?: 'n-a';
    }
}

if (! function_exists('array_get')) {
    function array_get(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }
}

if (! function_exists('abort')) {
    function abort(int $status, string $message = ''): never
    {
        throw new \App\Core\Exceptions\HttpException($status, $message);
    }
}

if (! function_exists('abort_unless')) {
    function abort_unless(bool $condition, int $status, string $message = ''): void
    {
        if (! $condition) {
            abort($status, $message);
        }
    }
}

if (! function_exists('encrypt_value')) {
    function encrypt_value(string $value): string
    {
        return app('encrypter')->encrypt($value);
    }
}

if (! function_exists('decrypt_value')) {
    function decrypt_value(string $payload): string
    {
        return app('encrypter')->decrypt($payload);
    }
}
