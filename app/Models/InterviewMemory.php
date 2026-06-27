<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * The rolling per-interview memory digest (docs/51 §4 Memory Engine). One row per
 * interview holds the running summary, the extracted skills, the detected
 * contradictions, a lightweight timeline, the confidence trend, and a token
 * estimate so the Orchestrator can keep the live prompt bounded. The append-only
 * detail lives in `interview_memory_items` (see InterviewMemoryItem).
 *
 * Tenant-scoped via `workspace_id`, uuid + timestamps.
 */
final class InterviewMemory extends Model
{
    protected static string $table = 'interview_memory';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'interview_id', 'summary', 'skills', 'contradictions',
        'timeline', 'confidence_trend', 'token_estimate',
    ];

    protected static array $casts = [
        'skills'           => 'array',
        'contradictions'   => 'array',
        'timeline'         => 'array',
        'confidence_trend' => 'array',
        'token_estimate'   => 'int',
    ];
}
