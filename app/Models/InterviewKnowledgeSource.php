<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A grounding fact the interview agents read (docs/51 §11 Knowledge Engine): a job
 * description, company info, evaluation criteria, required skills, a blueprint,
 * scoring rules, a policy, or the hiring workflow. Active sources are concatenated
 * by the KnowledgeEngine into a trusted grounding block for the Orchestrator.
 *
 * Tenant-scoped via `workspace_id`, uuid + timestamps.
 */
final class InterviewKnowledgeSource extends Model
{
    protected static string $table = 'interview_knowledge_sources';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'interview_id', 'source_type', 'title', 'content',
        'meta', 'is_active', 'sort_order',
    ];

    protected static array $casts = [
        'meta'       => 'array',
        'is_active'  => 'bool',
        'sort_order' => 'int',
    ];
}
