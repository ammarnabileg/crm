<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Permissions\Application;

use HaHireAI\Modules\Permissions\Domain\PermissionCatalog;
use HaHireAI\Modules\Permissions\Infrastructure\PermissionRepository;

/** Seeds the permission catalog during installation (idempotent). */
final class PermissionSeeder
{
    public function __construct(private readonly PermissionRepository $permissions)
    {
    }

    public function seed(): int
    {
        return $this->permissions->seed(PermissionCatalog::all());
    }
}
