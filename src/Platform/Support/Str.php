<?php

declare(strict_types=1);

namespace Nizam\Platform\Support;

/**
 * Pure, side-effect-free string helpers used across the platform.
 *
 * All methods are static and deterministic (the only exception, {@see self::uuid()}, delegates to
 * {@see Uuid} for id generation). Case conversions follow the platform conventions: DB identifiers
 * are snake_case, class names StudlyCase, and method/property names camelCase.
 */
final class Str
{
    /**
     * Non-instantiable static utility.
     */
    private function __construct()
    {
    }

    /**
     * Convert a string to snake_case (e.g. "TenantUserId" -> "tenant_user_id").
     */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        if ($value === '') {
            return $value;
        }

        // Normalise existing separators to spaces so mixed inputs collapse cleanly.
        $value = str_replace(['-', '_'], ' ', $value);
        $value = (string) preg_replace('/\s+/', '', ucwords($value));

        $snake = (string) preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value);

        return mb_strtolower($snake, 'UTF-8');
    }

    /**
     * Convert a string to camelCase (e.g. "tenant_user_id" -> "tenantUserId").
     */
    public static function camel(string $value): string
    {
        return lcfirst(self::studly($value));
    }

    /**
     * Convert a string to StudlyCase / PascalCase (e.g. "tenant_user_id" -> "TenantUserId").
     */
    public static function studly(string $value): string
    {
        $words = (string) preg_replace('/[-_\s]+/', ' ', $value);

        return str_replace(' ', '', ucwords($words));
    }

    /**
     * Convert a string to a URL-safe, lowercase slug.
     */
    public static function slug(string $value, string $separator = '-'): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = (string) preg_replace('/[^a-z0-9]+/u', $separator, $value);

        return trim($value, $separator);
    }

    /**
     * Whether $haystack begins with $needle.
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return $needle !== '' && str_starts_with($haystack, $needle);
    }

    /**
     * Whether $haystack ends with $needle.
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        return $needle !== '' && str_ends_with($haystack, $needle);
    }

    /**
     * Whether $haystack contains $needle.
     */
    public static function contains(string $haystack, string $needle): bool
    {
        return $needle !== '' && str_contains($haystack, $needle);
    }

    /**
     * Generate a new UUID v7 string.
     */
    public static function uuid(): string
    {
        return Uuid::v7();
    }
}
