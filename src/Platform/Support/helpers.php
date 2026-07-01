<?php

declare(strict_types=1);

use Nizam\Platform\Support\Str;
use Nizam\Platform\Support\Uuid;

/*
 * A deliberately small set of pure, global convenience helpers.
 *
 * Each function is guarded by function_exists() so this file is safe to autoload more than once
 * (e.g. under Composer's "files" autoloading) and so a host application may pre-define its own.
 * Nothing here performs I/O; anything stateful lives behind the container instead.
 */

if (!function_exists('str_studly')) {
    /**
     * Convert a string to StudlyCase / PascalCase.
     */
    function str_studly(string $value): string
    {
        return Str::studly($value);
    }
}

if (!function_exists('str_snake')) {
    /**
     * Convert a string to snake_case.
     */
    function str_snake(string $value): string
    {
        return Str::snake($value);
    }
}

if (!function_exists('uuid7')) {
    /**
     * Generate a new UUID v7 string.
     */
    function uuid7(): string
    {
        return Uuid::v7();
    }
}

if (!function_exists('value')) {
    /**
     * Resolve a value: invoke it if it is a closure, otherwise return it unchanged.
     *
     * @param mixed ...$args Arguments forwarded to the closure.
     */
    function value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}
