<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A reference / sample answer for a Question Bank question (docs/51 §10). Used to
 * guide interviewers and to anchor AI scoring: `is_model_answer` flags the canonical
 * answer, `score_hint` suggests the score a matching answer should earn. Tenant-scoped,
 * uuid + stamped.
 */
final class QuestionReferenceAnswer extends Model
{
    protected static string $table = 'question_reference_answers';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'question_id', 'answer', 'is_model_answer', 'score_hint', 'notes',
    ];

    protected static array $casts = [
        'is_model_answer' => 'bool',
        'score_hint'      => 'float',
    ];
}
