<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AiCredential;

/**
 * Repository for per-tenant AI provider credentials. AiCredential uses soft
 * deletes, so this repository inherits trash/restore behavior from
 * BaseRepository — a key removed by a tenant can be recovered.
 */
final class AiCredentialRepository extends BaseRepository
{
    protected string $model = AiCredential::class;
}
