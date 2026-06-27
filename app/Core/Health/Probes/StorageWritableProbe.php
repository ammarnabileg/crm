<?php

declare(strict_types=1);

namespace HaHireAI\Core\Health\Probes;

use HaHireAI\Core\Contracts\HealthProbe;
use HaHireAI\Core\Health\HealthResult;

/** Verifies the storage path exists and is writable. */
final class StorageWritableProbe implements HealthProbe
{
    public function __construct(private readonly string $path)
    {
    }

    public function name(): string
    {
        return 'storage.writable';
    }

    public function severity(): string
    {
        return 'critical';
    }

    public function check(): HealthResult
    {
        if (! is_dir($this->path)) {
            return HealthResult::unhealthy("Storage path missing: {$this->path}");
        }

        return is_writable($this->path)
            ? HealthResult::ok('Storage is writable')
            : HealthResult::unhealthy("Storage not writable: {$this->path}");
    }
}
