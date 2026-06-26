<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Configuration repository.
 *
 * Loads every PHP file in the config directory into a single dot-accessible
 * store, e.g. config('database.connections.mysql.host'). Files return an
 * associative array and are keyed by filename.
 */
final class Config
{
    private array $items = [];

    public function __construct(string $configPath)
    {
        foreach (glob($configPath . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');
            $this->items[$key] = require $file;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref = &$this->items;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $ref[$segment] = $value;
                break;
            }
            if (! isset($ref[$segment]) || ! is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
    }

    public function has(string $key): bool
    {
        return $this->get($key, '__missing__') !== '__missing__';
    }

    public function all(): array
    {
        return $this->items;
    }
}
