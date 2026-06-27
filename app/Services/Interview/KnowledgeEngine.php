<?php

declare(strict_types=1);

namespace App\Services\Interview;

use App\Models\InterviewKnowledgeSource;

/**
 * Knowledge Engine (docs/51 §11) — the interview's grounding store.
 *
 * Sources (job description, company info, evaluation criteria, required skills,
 * blueprint, scoring rules, policy, hiring workflow) are persisted per interview and
 * concatenated by buildKnowledgeContext() into a single trusted grounding block the
 * Orchestrator prepends to the agent's system message. This is what keeps the
 * interviewer factual and on-brief rather than free-styling.
 *
 * Array/JSON columns are json_encode()d on write because the Model only casts JSON
 * ON READ (see App\Core\Model::castAttribute).
 */
final class KnowledgeEngine
{
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * Add (or stack) a grounding source for an interview. Appended at the end of
     * the sort order.
     *
     * @param array<string,mixed> $meta
     */
    public function addSource(int $interviewId, string $sourceType, string $title, string $content, array $meta = []): InterviewKnowledgeSource
    {
        return InterviewKnowledgeSource::create([
            'interview_id' => $interviewId,
            'source_type'  => $sourceType,
            'title'        => $title !== '' ? $title : null,
            'content'      => $content,
            'meta'         => json_encode($meta, self::JSON_FLAGS),
            'is_active'    => 1,
            'sort_order'   => $this->nextSortOrder($interviewId),
        ]);
    }

    /**
     * All ACTIVE sources for an interview in display order (sort_order, then id).
     *
     * @return array<int, array<string,mixed>>
     */
    public function sourcesFor(int $interviewId): array
    {
        return InterviewKnowledgeSource::query()
            ->where('interview_id', '=', $interviewId)
            ->where('is_active', '=', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Concatenate the active sources into a single grounding block. Empty string
     * when the interview has no active sources (so callers can append it
     * unconditionally).
     */
    public function buildKnowledgeContext(int $interviewId): string
    {
        $sources = $this->sourcesFor($interviewId);
        if ($sources === []) {
            return '';
        }

        $blocks = [];
        foreach ($sources as $source) {
            $type = strtoupper((string) ($source['source_type'] ?? 'source'));
            $title = trim((string) ($source['title'] ?? ''));
            $heading = $title !== '' ? "{$type} — {$title}" : $type;
            $content = trim((string) ($source['content'] ?? ''));
            $blocks[] = "[{$heading}]\n{$content}";
        }

        return "Grounding knowledge for this interview:\n\n" . implode("\n\n", $blocks);
    }

    /**
     * Next sort_order for the interview's sources (scoped to the tenant). 0-based;
     * branches on existence because QueryBuilder::max() returns 0 for an empty set.
     */
    private function nextSortOrder(int $interviewId): int
    {
        $query = InterviewKnowledgeSource::query()->where('interview_id', '=', $interviewId);
        if ($query->count() === 0) {
            return 0;
        }

        $max = (int) InterviewKnowledgeSource::query()
            ->where('interview_id', '=', $interviewId)
            ->max('sort_order');

        return $max + 1;
    }
}
