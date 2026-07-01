<?php

declare(strict_types=1);

namespace Nizam\Platform\Config;

/**
 * A dot-access configuration repository over a nested array.
 *
 * Configuration is loaded once (typically at bootstrap) into an immutable-ish tree and read with
 * dotted keys, e.g. `get('logging.channels.app.path')`. This decouples the rest of the platform
 * from where configuration originates (files, env, arrays) — everything depends on this repository.
 */
final class Config
{
    /**
     * @param array<string, mixed> $items The full configuration tree.
     */
    public function __construct(private array $items = [])
    {
    }

    /**
     * Read a value by dotted key, returning $default when the key is absent.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        $segments = explode('.', $key);
        $current = $this->items;

        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];

                continue;
            }

            return $default;
        }

        return $current;
    }

    /**
     * Whether a value exists for the dotted key (including an explicit null).
     */
    public function has(string $key): bool
    {
        if (array_key_exists($key, $this->items)) {
            return true;
        }

        $segments = explode('.', $key);
        $current = $this->items;

        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];

                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * Set a value by dotted key, creating intermediate arrays as needed.
     */
    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $target = &$this->items;

        while (count($segments) > 1) {
            $segment = array_shift($segments);

            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $target[array_shift($segments)] = $value;
    }

    /**
     * The entire configuration tree.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }
}
