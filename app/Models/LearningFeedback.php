<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A captured learning signal (docs/51 §18 Continuous Learning): feedback the engine
 * will LATER learn from — a human correction, a real-world outcome, or a system
 * observation — tied to an interview and/or a decision, with an optional rating and a
 * structured correction. No model retraining happens here; P10 only captures and
 * aggregates this signal, strictly within the tenant boundary. Tenant-scoped.
 */
final class LearningFeedback extends Model
{
    protected static string $table = 'learning_feedback';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'interview_id', 'decision_id', 'source', 'rating', 'correction',
        'notes', 'created_by',
    ];

    protected static array $casts = [
        'correction' => 'array',
        'rating'     => 'float',
    ];
}
