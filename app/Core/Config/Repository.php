<?php

declare(strict_types=1);

namespace HaHireAI\Core\Config;

/**
 * Dot-notation configuration repository, populated from config/*.php files
 * (each returns an array). No constants or globals are used in application
 * code — everything reads through here. See docs/CONFIGURATION_GUIDE.md.
 */
final class Repository
{
    /** @param array<string, mixed> $items */
    public function __construct(private array $items = [])
    {
    }

    /** Load every `*.php` file in a directory as `config[<basename>] = <returned array>`. */
    public function loadDirectory(string $directory): self
    {
        if (! is_dir($directory)) {
            return $this;
        }

        foreach (glob(rtrim($directory, '/') . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');
            $value = require $file;
            if (is_array($value)) {
                $this->items[$key] = $value;
            }
        }

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        $value = $this->items;

        foreach (explode('.', $key) as $segment) {
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
            if ($i === array_key_last($segments)) {
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

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->items;
    }
}
