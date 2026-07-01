<?php

declare(strict_types=1);

namespace HaHireAI\Core\Http\Session;

use RuntimeException;

/**
 * Resolves a SessionStore for the configured driver. Ships with the 'file'
 * driver; 'redis'/'database' can be added at runtime without touching the
 * Session wrapper:
 *
 *     $factory->extend('redis', fn (SessionConfig $c) => new RedisSessionStore(...));
 *
 * An unknown driver fails loudly at boot with a clear message rather than
 * silently falling back to PHP's system session path.
 */
final class SessionStoreFactory
{
    /** @var array<string, callable(SessionConfig): SessionStore> */
    private array $drivers = [];

    public function __construct()
    {
        $this->extend('file', static fn (SessionConfig $config): SessionStore => new FileSessionStore($config->filePath()));
    }

    /** @param callable(SessionConfig): SessionStore $factory */
    public function extend(string $driver, callable $factory): void
    {
        $this->drivers[strtolower($driver)] = $factory;
    }

    public function make(SessionConfig $config): SessionStore
    {
        $driver = $config->driver();

        if (! isset($this->drivers[$driver])) {
            $known = implode(', ', array_keys($this->drivers));

            throw new RuntimeException(
                "Unsupported session driver [{$driver}]. Register it via SessionStoreFactory::extend(). Available: {$known}."
            );
        }

        return ($this->drivers[$driver])($config);
    }
}
