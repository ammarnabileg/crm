<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Question;
use App\Services\Question\QuestionBank;
use Tests\TestCase;

/**
 * AI Interview Engine — Question Bank Engine (docs/51 §10).
 *
 * Covers adding a classified question with tags + reference answers, tag-aware and
 * attribute search, source filtering, and the static + AI + company source mixing
 * that feeds interview assembly. Tenant-scoped throughout (the bank is a reusable
 * library, distinct from the D7 per-interview `interview_questions`).
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function setUp(): void
    {
        // DB-backed tests need an active tenant.
        $workspaceId = (int) app('db')->table('workspaces')->orderBy('id')->value('id');
        tenant()->setById($workspaceId);
    }

    private function bank(): QuestionBank
    {
        return new QuestionBank();
    }

    /**
     * Seed a small, varied pool: differing sources, difficulties and tags. Returns
     * the created questions keyed by a short handle for assertions.
     *
     * @return array<string, Question>
     */
    private function seedPool(): array
    {
        $bank = $this->bank();

        return [
            'static_easy' => $bank->add(
                [
                    'question_type' => 'technical',
                    'text'          => 'What is a primary key?',
                    'difficulty'    => 'easy',
                    'language'      => 'en',
                    'source'        => 'static',
                    'competency'    => 'databases',
                ],
                ['sql', 'fundamentals'],
            ),
            'static_hard' => $bank->add(
                [
                    'question_type' => 'technical',
                    'text'          => 'Design a sharded counter.',
                    'difficulty'    => 'hard',
                    'language'      => 'en',
                    'source'        => 'static',
                    'competency'    => 'system_design',
                ],
                ['system-design'],
            ),
            'company_medium' => $bank->add(
                [
                    'question_type' => 'behavioral',
                    'text'          => 'Tell us about a time you shipped under deadline.',
                    'difficulty'    => 'medium',
                    'language'      => 'en',
                    'source'        => 'company',
                ],
                ['leadership'],
            ),
            'ai_medium' => $bank->add(
                [
                    'question_type' => 'scenario',
                    'text'          => 'A service is down at 2am — walk me through your response.',
                    'difficulty'    => 'medium',
                    'language'      => 'en',
                    'source'        => 'ai',
                ],
                ['leadership', 'incident'],
            ),
        ];
    }

    public function test_add_persists_question_with_tags_and_reference_answer(): void
    {
        $question = $this->bank()->add(
            [
                'question_type'   => 'technical',
                'text'            => 'Explain database indexing.',
                'difficulty'      => 'medium',
                'language'        => 'en',
                'job_family'      => 'engineering',
                'source'          => 'static',
                'expected_skills' => ['sql', 'performance'],
                'competency'      => 'databases',
            ],
            ['sql', ['key' => 'performance', 'label' => 'Performance']],
            [
                ['answer' => 'An index is a sorted lookup structure.', 'is_model_answer' => true, 'score_hint' => 4.5],
            ],
        );

        // Persisted + readable back through the tenant-scoped model.
        $reloaded = Question::find((int) $question->getKey());
        $this->assertNotNull($reloaded);
        $this->assertSame('Explain database indexing.', (string) $reloaded->text);
        $this->assertSame('static', (string) $reloaded->source);
        // expected_skills round-trips through the JSON column (cast on read).
        $this->assertSame(['sql', 'performance'], $reloaded->expected_skills);

        // Tags linked via the pivot.
        $tags = $reloaded->tags();
        $this->assertSame(2, count($tags));
        $tagKeys = array_map(static fn ($t): string => (string) $t['key'], $tags);
        $this->assertTrue(in_array('sql', $tagKeys, true));
        $this->assertTrue(in_array('performance', $tagKeys, true));

        // Reference answer persisted.
        $answers = $reloaded->referenceAnswers();
        $this->assertSame(1, count($answers));
        $this->assertSame('An index is a sorted lookup structure.', (string) $answers[0]['answer']);
        $this->assertSame(1, (int) $answers[0]['is_model_answer']);
    }

    public function test_search_filters_by_difficulty(): void
    {
        $this->seedPool();

        $hard = $this->bank()->search(['difficulty' => 'hard']);
        $this->assertSame(1, count($hard));
        $this->assertSame('Design a sharded counter.', (string) $hard[0]->text);

        $medium = $this->bank()->search(['difficulty' => 'medium']);
        $this->assertSame(2, count($medium));
    }

    public function test_search_filters_by_tag(): void
    {
        $this->seedPool();

        // 'leadership' tags the company + ai questions.
        $leadership = $this->bank()->search(['tag' => 'leadership']);
        $this->assertSame(2, count($leadership));

        // 'sql' tags only the static_easy question.
        $sql = $this->bank()->search(['tag' => 'sql']);
        $this->assertSame(1, count($sql));
        $this->assertSame('What is a primary key?', (string) $sql[0]->text);

        // A question must carry ALL requested tags.
        $both = $this->bank()->search(['tags' => ['leadership', 'incident']]);
        $this->assertSame(1, count($both));
        $this->assertSame('A service is down at 2am — walk me through your response.', (string) $both[0]->text);

        // An unknown tag matches nothing.
        $this->assertSame([], $this->bank()->search(['tag' => 'does-not-exist']));
    }

    public function test_search_filters_by_source(): void
    {
        $this->seedPool();

        $this->assertSame(2, count($this->bank()->search(['source' => 'static'])));
        $this->assertSame(1, count($this->bank()->search(['source' => 'company'])));
        $this->assertSame(1, count($this->bank()->search(['source' => 'ai'])));
    }

    public function test_mix_returns_up_to_limit_blending_sources(): void
    {
        $this->seedPool();

        // Limit of 3 with one company + one ai + two static available → a blend that
        // round-robins across sources (static, company, ai) before topping up.
        $mixed = $this->bank()->mix([], 3);
        $this->assertSame(3, count($mixed));

        $sources = array_map(static fn (Question $q): string => (string) $q->source, $mixed);
        // Every source represented in the first pass of the round-robin.
        $this->assertTrue(in_array('static', $sources, true));
        $this->assertTrue(in_array('company', $sources, true));
        $this->assertTrue(in_array('ai', $sources, true));

        // No duplicate questions in the blend.
        $ids = array_map(static fn (Question $q): int => (int) $q->getKey(), $mixed);
        $this->assertSame(count($ids), count(array_unique($ids)));

        // The limit is an upper bound: asking for more than exist returns all 4.
        $all = $this->bank()->mix([], 10);
        $this->assertSame(4, count($all));
    }
};
