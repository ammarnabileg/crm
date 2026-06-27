<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A per-tenant tag in the Question Bank tag dictionary (docs/51 §10) — e.g.
 * "leadership", "sql", "remote-friendly". Linked to questions via the
 * `question_taggables` pivot. `key` is unique within a workspace. Tenant-scoped,
 * uuid + stamped.
 */
final class QuestionTag extends Model
{
    protected static string $table = 'question_tags';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'key', 'label',
    ];
}
