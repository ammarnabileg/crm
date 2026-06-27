<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * One append-only entry in an interview's memory log (docs/51 §4 Memory Engine):
 * a message, an extracted skill, a detected contradiction, a free note, or a score.
 * Ordered by `sequence`. There is no `updated_at` — entries are immutable once
 * written (only `created_at` is stamped).
 *
 * Tenant-scoped via `workspace_id`, uuid.
 */
final class InterviewMemoryItem extends Model
{
    protected static string $table = 'interview_memory_items';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $timestamps = false;

    protected static array $fillable = [
        'interview_id', 'memory_id', 'role', 'item_type',
        'content', 'meta', 'sequence',
    ];

    protected static array $casts = [
        'meta'     => 'array',
        'sequence' => 'int',
    ];
}
