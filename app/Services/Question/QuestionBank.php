<?php

declare(strict_types=1);

namespace App\Services\Question;

use App\Models\Question;
use App\Models\QuestionReferenceAnswer;
use App\Models\QuestionTag;
use RuntimeException;

/**
 * Question Bank service (docs/51 §10, AI Interview Engine).
 *
 * The Question Bank is a reusable, classified, taggable library of interview
 * questions (distinct from the D7 `interview_questions` table of asked questions).
 * This service:
 *  - ADDS a question with its tags (upserted into the tenant tag dictionary and
 *    linked via the `question_taggables` pivot) and its reference answers;
 *  - TAGS an existing question;
 *  - SEARCHES the bank with config-driven filters (type, difficulty, language,
 *    job family, source, competency, and by tag key);
 *  - MIXES a blended set drawing from `static`, `company` and `ai` sourced
 *    questions (docs/51 §10's static + AI + company mixing).
 *
 * All operations go through the tenant-scoped Model layer, so isolation is
 * automatic and fail-closed.
 */
final class QuestionBank
{
    /** Question sources the engine blends when assembling an interview. */
    private const SOURCES = ['static', 'company', 'ai'];

    /** Accepted question types (code-validated; no ENUM column). */
    private const QUESTION_TYPES = [
        'technical', 'behavioral', 'scenario', 'situational', 'culture', 'language',
    ];

    /** Accepted difficulty keys (code-validated; no ENUM column). */
    private const DIFFICULTIES = ['easy', 'medium', 'hard'];

    /**
     * Add a question to the bank, upserting its tags and inserting its reference
     * answers. Returns the persisted Question.
     *
     * @param array<string,mixed>            $attributes  question_bank columns
     * @param array<int, array{key:string,label:string}|string> $tags  tags to link
     * @param array<int, array<string,mixed>> $referenceAnswers reference-answer rows
     */
    public function add(array $attributes, array $tags = [], array $referenceAnswers = []): Question
    {
        $type = (string) ($attributes['question_type'] ?? '');
        if (! in_array($type, self::QUESTION_TYPES, true)) {
            throw new RuntimeException("Unsupported question_type '{$type}'.");
        }

        $text = trim((string) ($attributes['text'] ?? ''));
        if ($text === '') {
            throw new RuntimeException('A question needs non-empty text.');
        }

        $source = (string) ($attributes['source'] ?? 'static');
        if (! in_array($source, self::SOURCES, true)) {
            throw new RuntimeException("Unsupported source '{$source}'.");
        }

        $difficulty = $attributes['difficulty'] ?? null;
        if ($difficulty !== null && ! in_array((string) $difficulty, self::DIFFICULTIES, true)) {
            throw new RuntimeException("Unsupported difficulty '{$difficulty}'.");
        }

        // CASTS APPLY ON READ ONLY → json_encode the JSON column before create().
        $expectedSkills = $attributes['expected_skills'] ?? null;
        if (is_array($expectedSkills)) {
            $expectedSkills = json_encode($expectedSkills, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $question = Question::create([
            'question_type'   => $type,
            'text'            => $text,
            'difficulty'      => $difficulty,
            'language'        => $attributes['language'] ?? null,
            'job_family'      => $attributes['job_family'] ?? null,
            'department'      => $attributes['department'] ?? null,
            'source'          => $source,
            'expected_skills' => $expectedSkills,
            'competency'      => $attributes['competency'] ?? null,
            'is_active'       => $attributes['is_active'] ?? 1,
            'created_by'      => $attributes['created_by'] ?? null,
        ]);

        $questionId = (int) $question->getKey();

        foreach ($tags as $tag) {
            if (is_string($tag)) {
                $this->tag($questionId, $tag, $this->labelFromKey($tag));
            } else {
                $key = (string) ($tag['key'] ?? '');
                if ($key === '') {
                    continue;
                }
                $this->tag($questionId, $key, (string) ($tag['label'] ?? $this->labelFromKey($key)));
            }
        }

        foreach ($referenceAnswers as $answer) {
            $answerText = trim((string) ($answer['answer'] ?? ''));
            if ($answerText === '') {
                continue;
            }
            QuestionReferenceAnswer::create([
                'question_id'     => $questionId,
                'answer'          => $answerText,
                'is_model_answer' => $answer['is_model_answer'] ?? 0,
                'score_hint'      => $answer['score_hint'] ?? null,
                'notes'           => $answer['notes'] ?? null,
            ]);
        }

        return $question;
    }

    /**
     * Tag a question: upsert the tag by key into the tenant tag dictionary, then
     * link it to the question (idempotent — re-tagging is a no-op).
     */
    public function tag(int $questionId, string $key, string $label): void
    {
        $key = trim($key);
        if ($key === '') {
            throw new RuntimeException('A tag needs a non-empty key.');
        }
        $label = trim($label) !== '' ? trim($label) : $this->labelFromKey($key);

        // Upsert the tag by (workspace, key) — tenant scoping is automatic.
        $existing = QuestionTag::query()->where('key', '=', $key)->first();
        $tagId = $existing !== null
            ? (int) $existing['id']
            : (int) QuestionTag::create(['key' => $key, 'label' => $label])->getKey();

        // Link via the pivot, avoiding a duplicate (the table also enforces UNIQUE).
        $alreadyLinked = app('db')->table('question_taggables')
            ->where('workspace_id', '=', tenant()->id())
            ->where('question_id', '=', $questionId)
            ->where('tag_id', '=', $tagId)
            ->first();

        if ($alreadyLinked !== null) {
            return;
        }

        app('db')->table('question_taggables')->insert([
            'uuid'         => Question::generateUuid(),
            'workspace_id' => tenant()->id(),
            'question_id'  => $questionId,
            'tag_id'       => $tagId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    /**
     * Search the bank. All filters are optional and combine with AND; an empty
     * filter set returns every active-or-not question in the tenant.
     *
     * Supported filters: question_type, difficulty, language, job_family, source,
     * competency, is_active, and `tag` (a single tag key) / `tags` (a list of tag
     * keys — a question must carry ALL of them).
     *
     * @param array<string,mixed> $filters
     * @return Question[]
     */
    public function search(array $filters = []): array
    {
        $query = Question::query();

        foreach (['question_type', 'difficulty', 'language', 'job_family', 'source', 'competency'] as $column) {
            if (isset($filters[$column]) && $filters[$column] !== '') {
                $query->where($column, '=', $filters[$column]);
            }
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', '=', $filters['is_active'] ? 1 : 0);
        }

        // Tag filtering: resolve matching question ids through the pivot (tenant
        // scoped) and constrain the main query, keeping the tenant predicate on
        // `question_bank` unambiguous (no JOIN against the scoped builder).
        $tagKeys = $this->tagKeysFromFilters($filters);
        if ($tagKeys !== []) {
            $questionIds = $this->questionIdsForTags($tagKeys);
            if ($questionIds === []) {
                return [];
            }
            $query->whereIn('id', $questionIds);
        }

        if (isset($filters['limit'])) {
            $query->limit((int) $filters['limit']);
        }

        $query->orderBy('id', 'desc');

        return array_map([Question::class, 'hydrate'], $query->get());
    }

    /**
     * Build a blended question set, drawing from `static`, `company` and `ai`
     * sources as available (docs/51 §10's static + AI + company mixing). Round-robins
     * across sources so no single source dominates a short list, falling back to
     * whatever is available to fill the limit.
     *
     * @param array<string,mixed> $filters base filters (source is ignored here)
     * @return Question[]
     */
    public function mix(array $filters, int $limit): array
    {
        $limit = max(0, $limit);
        if ($limit === 0) {
            return [];
        }

        // Per-source candidate pools (source filter overridden by the mix).
        $pools = [];
        foreach (self::SOURCES as $source) {
            $sourceFilters = $filters;
            unset($sourceFilters['limit']);
            $sourceFilters['source'] = $source;
            $pools[$source] = $this->search($sourceFilters);
        }

        // Round-robin across sources for a balanced blend.
        $mixed = [];
        $seen = [];
        $exhausted = false;
        while (count($mixed) < $limit && ! $exhausted) {
            $exhausted = true;
            foreach (self::SOURCES as $source) {
                if ($pools[$source] === []) {
                    continue;
                }
                $exhausted = false;
                /** @var Question $question */
                $question = array_shift($pools[$source]);
                $id = (int) $question->getKey();
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $mixed[] = $question;
                if (count($mixed) >= $limit) {
                    break;
                }
            }
        }

        return $mixed;
    }

    /**
     * Resolve the requested tag keys from a filter set ('tag' string and/or 'tags'
     * list).
     *
     * @param array<string,mixed> $filters
     * @return string[]
     */
    private function tagKeysFromFilters(array $filters): array
    {
        $keys = [];
        if (isset($filters['tag']) && $filters['tag'] !== '') {
            $keys[] = (string) $filters['tag'];
        }
        foreach ((array) ($filters['tags'] ?? []) as $key) {
            if ((string) $key !== '') {
                $keys[] = (string) $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Question ids carrying ALL of the given tag keys (tenant scoped). Returns an
     * empty array if any tag is unknown or no question matches.
     *
     * @param string[] $tagKeys
     * @return int[]
     */
    private function questionIdsForTags(array $tagKeys): array
    {
        $tagIds = QuestionTag::query()->whereIn('key', $tagKeys)->pluck('id');
        // A question must carry every requested tag, so every key must resolve.
        if (count($tagIds) < count($tagKeys)) {
            return [];
        }

        $rows = app('db')->table('question_taggables')
            ->where('workspace_id', '=', tenant()->id())
            ->whereIn('tag_id', array_map('intval', $tagIds))
            ->get();

        $countPerQuestion = [];
        foreach ($rows as $row) {
            $questionId = (int) $row['question_id'];
            $countPerQuestion[$questionId] = ($countPerQuestion[$questionId] ?? 0) + 1;
        }

        $required = count($tagIds);
        $matching = [];
        foreach ($countPerQuestion as $questionId => $count) {
            if ($count >= $required) {
                $matching[] = $questionId;
            }
        }

        return $matching;
    }

    /** Derive a human label from a tag key (e.g. "remote-friendly" → "Remote Friendly"). */
    private function labelFromKey(string $key): string
    {
        $label = trim(str_replace(['-', '_'], ' ', $key));

        return $label === '' ? $key : ucwords($label);
    }
}
