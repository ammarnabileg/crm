<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * One immutable execution record of an interview agent (docs/51 §15, Explainable
 * AI). Captures what a single agent decided for an interview — its score /
 * max_score / confidence, the structured `verdict`, the human `rationale`, and the
 * model + token usage that produced it. Tenant-scoped; has no updated_at because a
 * run is an audit fact, never edited (same principle as workflow_run_steps).
 */
final class AgentRun extends Model
{
    protected static string $table = 'agent_runs';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $timestamps = false;

    protected static array $fillable = [
        'interview_id', 'agent_key', 'agent_type',
        'score', 'max_score', 'confidence', 'verdict', 'rationale',
        'model_key', 'prompt_tokens', 'completion_tokens', 'created_at',
    ];

    protected static array $casts = [
        'verdict'    => 'array',
        'score'      => 'float',
        'max_score'  => 'float',
        'confidence' => 'float',
    ];
}
