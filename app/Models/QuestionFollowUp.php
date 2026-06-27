<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A conditional follow-up prompt for a Question Bank question (docs/51 §10). A
 * follow-up is either free-text (`follow_up_text`) or a pointer to another bank
 * question (`follow_up_question_id`), optionally gated by a `condition` (e.g. only
 * ask when the prior answer scored below a threshold). Tenant-scoped, uuid + stamped.
 */
final class QuestionFollowUp extends Model
{
    protected static string $table = 'question_follow_ups';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'question_id', 'follow_up_text', 'follow_up_question_id', 'condition', 'sort_order',
    ];

    protected static array $casts = [
        'condition'  => 'array',
        'sort_order' => 'int',
    ];
}
