<?php

declare(strict_types=1);

namespace HaHireAI\Core\Config;

/**
 * Minimal .env loader. Values are read once at boot; the rest of the system
 * reads configuration through {@see Repository}, never env() directly.
 * See docs/CONFIGURATION_GUIDE.md.
 */
final class Environment
{
    /** @var array<string, string> */
    private array $vars = [];

    private bool $loaded = false;

    public function load(string $path): self
    {
        if (! is_file($path)) {
            $this->loaded = true;

            return $this;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = $this->stripQuotes(trim($value));

            $this->vars[$key] = $value;
        }

        $this->loaded = true;

        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        // Real environment variables take precedence over the .env file, so
        // CI/test/deploy environments can override committed defaults.
        $fromEnv = getenv($key);

        if ($fromEnv !== false) {
            return $this->cast($fromEnv);
        }

        if (array_key_exists($key, $this->vars)) {
            return $this->cast($this->vars[$key]);
        }

        return $default;
    }

    public function has(string $key): bool
    {
        return getenv($key) !== false || array_key_exists($key, $this->vars);
    }

    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    /**
     * Validate that required keys are present; returns the list of missing keys.
     *
     * @param  list<string>  $required
     * @return list<string>
     */
    public function validate(array $required): array
    {
        return array_values(array_filter($required, fn (string $key): bool => ! $this->has($key)));
    }

    private function cast(string $value): mixed
    {
        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }

    private function stripQuotes(string $value): string
    {
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }
}
