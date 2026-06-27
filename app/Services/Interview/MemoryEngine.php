<?php

declare(strict_types=1);

namespace App\Services\Interview;

use App\Models\InterviewMemory;
use App\Models\InterviewMemoryItem;

/**
 * Memory Engine (docs/51 §4) — the interview's persistent, BOUNDED memory.
 *
 * Every interview gets exactly one rolling `interview_memory` digest (summary +
 * extracted skills + detected contradictions) plus an append-only
 * `interview_memory_items` log. The key guarantee is buildContext(): it returns the
 * summary (as a single system note) plus only the most recent N items — never the
 * full transcript — so the live prompt stays capped no matter how long the
 * interview runs.
 *
 * Array/JSON columns are json_encode()d on write because the Model only casts JSON
 * ON READ (see App\Core\Model::castAttribute).
 */
final class MemoryEngine
{
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /** Get the interview's memory row, creating an empty one on first use. */
    public function forInterview(int $interviewId): InterviewMemory
    {
        $row = InterviewMemory::query()
            ->where('interview_id', '=', $interviewId)
            ->first();
        if ($row !== null) {
            return InterviewMemory::hydrate($row);
        }

        return InterviewMemory::create([
            'interview_id'     => $interviewId,
            'summary'          => null,
            'skills'           => json_encode([], self::JSON_FLAGS),
            'contradictions'   => json_encode([], self::JSON_FLAGS),
            'timeline'         => json_encode([], self::JSON_FLAGS),
            'confidence_trend' => json_encode([], self::JSON_FLAGS),
            'token_estimate'   => 0,
        ]);
    }

    /**
     * Append an immutable entry to the interview's memory log with the next
     * sequence number.
     *
     * @param array<string,mixed> $meta
     */
    public function appendItem(int $interviewId, string $role, string $type, string $content, array $meta = []): void
    {
        $memory = $this->forInterview($interviewId);

        InterviewMemoryItem::create([
            'interview_id' => $interviewId,
            'memory_id'    => (int) $memory->getKey(),
            'role'         => $role !== '' ? $role : null,
            'item_type'    => $type,
            'content'      => $content,
            'meta'         => json_encode($meta, self::JSON_FLAGS),
            'sequence'     => $this->nextSequence($interviewId),
            'created_at'   => now(),
        ]);
    }

    /** Convenience: record an extracted skill as a memory item. */
    public function recordSkill(int $interviewId, string $skill, array $meta = []): void
    {
        $this->appendItem($interviewId, 'system', 'skill', $skill, $meta);
    }

    /** Convenience: record a detected contradiction as a memory item. */
    public function recordContradiction(int $interviewId, string $contradiction, array $meta = []): void
    {
        $this->appendItem($interviewId, 'system', 'contradiction', $contradiction, $meta);
    }

    /**
     * Build the BOUNDED model context for a turn: the memory summary as a single
     * trusted system note (when present) followed by at most $maxItems of the most
     * recent log items, rendered oldest→newest as role/content messages.
     *
     * @return array<int, array{role:string, content:string, trusted?:bool}>
     */
    public function buildContext(int $interviewId, int $maxItems = 20): array
    {
        $maxItems = max(0, $maxItems);
        $memory = $this->forInterview($interviewId);

        $context = [];
        $summary = (string) ($memory->summary ?? '');
        if (trim($summary) !== '') {
            $context[] = [
                'role'    => 'system',
                'content' => 'Interview summary so far: ' . $summary,
                'trusted' => true,
            ];
        }

        if ($maxItems === 0) {
            return $context;
        }

        // Pull the most recent N (DESC + limit) then reverse to chronological order.
        $recent = InterviewMemoryItem::query()
            ->where('interview_id', '=', $interviewId)
            ->where('item_type', '=', 'message')
            ->orderBy('sequence', 'desc')
            ->orderBy('id', 'desc')
            ->limit($maxItems)
            ->get();

        foreach (array_reverse($recent) as $item) {
            $context[] = [
                'role'    => (string) ($item['role'] ?? 'user'),
                'content' => (string) ($item['content'] ?? ''),
            ];
        }

        return $context;
    }

    /**
     * Update the rolling digest (summary + skills + contradictions).
     *
     * @param array<int|string,mixed> $skills
     * @param array<int|string,mixed> $contradictions
     */
    public function updateSummary(int $interviewId, string $summary, array $skills = [], array $contradictions = []): void
    {
        $memory = $this->forInterview($interviewId);

        $memory->update([
            'summary'        => $summary,
            'skills'         => json_encode($skills, self::JSON_FLAGS),
            'contradictions' => json_encode($contradictions, self::JSON_FLAGS),
        ]);
    }

    /**
     * Next sequence number for the interview's memory log (scoped to the tenant).
     * 0-based: the first item is sequence 0. (QueryBuilder::max() returns 0 for an
     * empty set, which is indistinguishable from a real max of 0, so we branch on
     * existence first.)
     */
    private function nextSequence(int $interviewId): int
    {
        $query = InterviewMemoryItem::query()->where('interview_id', '=', $interviewId);
        if ($query->count() === 0) {
            return 0;
        }

        $max = (int) InterviewMemoryItem::query()
            ->where('interview_id', '=', $interviewId)
            ->max('sequence');

        return $max + 1;
    }
}
