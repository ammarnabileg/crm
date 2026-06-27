<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A reusable interview question from the Question Bank (docs/51 §10).
 *
 * Distinct from the D7 `interview_questions` table (the questions actually asked in a
 * given interview): the bank is a tenant-scoped, classified, taggable LIBRARY that the
 * AI Interview Engine draws from when assembling an interview, blending `static`,
 * `company` and `ai` sourced questions. Tenant-scoped, soft-deletable, uuid + stamped.
 */
final class Question extends Model
{
    protected static string $table = 'question_bank';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $softDeletes = true;

    protected static array $fillable = [
        'question_type', 'text', 'difficulty', 'language', 'job_family',
        'department', 'source', 'expected_skills', 'competency', 'is_active',
        'created_by',
    ];

    protected static array $casts = [
        'expected_skills' => 'array',
        'is_active'       => 'bool',
    ];

    /** @return array<int, array<string,mixed>> The tags linked to this question. */
    public function tags(): array
    {
        return self::db()->table('question_tags')
            ->select('question_tags.*')
            ->join('question_taggables', 'question_taggables.tag_id', '=', 'question_tags.id')
            ->where('question_taggables.question_id', '=', (int) $this->getKey())
            ->where('question_tags.workspace_id', '=', (int) $this->workspace_id)
            ->orderBy('question_tags.key')
            ->get();
    }

    /** @return array<int, array<string,mixed>> The reference / model answers. */
    public function referenceAnswers(): array
    {
        return self::db()->table('question_reference_answers')
            ->where('question_id', '=', (int) $this->getKey())
            ->where('workspace_id', '=', (int) $this->workspace_id)
            ->orderBy('id')
            ->get();
    }

    /** @return array<int, array<string,mixed>> The conditional follow-up prompts (ordered). */
    public function followUps(): array
    {
        return self::db()->table('question_follow_ups')
            ->where('question_id', '=', (int) $this->getKey())
            ->where('workspace_id', '=', (int) $this->workspace_id)
            ->orderBy('sort_order')
            ->get();
    }
}
