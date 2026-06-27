<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EvaluationFormVersion;
use App\Services\Evaluation\DecisionEngine;
use App\Services\Evaluation\EvaluationTemplate;
use RuntimeException;
use Tests\TestCase;

/**
 * AI Interview Engine P2 — Evaluation Templates + Decision Engine (docs/51 §3, §9).
 * Covers the pure weighted-scoring math, recommendation banding, immutable
 * template versioning, library instantiation, and decision persistence (with the
 * explainable per-criterion factor breakdown).
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function setUp(): void
    {
        // DB-backed tests need an active tenant; pure-scoring tests ignore it.
        $workspaceId = (int) app('db')->table('workspaces')->orderBy('id')->value('id');
        tenant()->setById($workspaceId);
    }

    private function engine(): DecisionEngine
    {
        return new DecisionEngine();
    }

    private function template(): EvaluationTemplate
    {
        return new EvaluationTemplate();
    }

    /** @return array<int, array<string,mixed>> */
    private function rubric(): array
    {
        return [
            ['key' => 'technical_depth', 'label' => 'Technical Depth', 'weight' => 30, 'max' => 5],
            ['key' => 'problem_solving', 'label' => 'Problem Solving', 'weight' => 25, 'max' => 5],
            ['key' => 'system_design',   'label' => 'System Design',   'weight' => 20, 'max' => 5],
            ['key' => 'communication',   'label' => 'Communication',   'weight' => 15, 'max' => 5],
            ['key' => 'culture_fit',     'label' => 'Culture Fit',     'weight' => 10, 'max' => 5],
        ];
    }

    // --- Pure scoring -------------------------------------------------------

    public function test_full_marks_normalizes_to_100(): void
    {
        $r = $this->engine()->score($this->rubric(), [
            'technical_depth' => 5, 'problem_solving' => 5, 'system_design' => 5,
            'communication' => 5, 'culture_fit' => 5,
        ], 70.0);

        $this->assertSame(100.0, $r['normalized_score']);
        $this->assertTrue($r['passed']);
        $this->assertSame('strong_yes', $r['recommendation_key']);
        $this->assertSame(5, $r['scored_count']);
    }

    public function test_uniform_80_percent_normalizes_to_80(): void
    {
        $r = $this->engine()->score($this->rubric(), [
            'technical_depth' => 4, 'problem_solving' => 4, 'system_design' => 4,
            'communication' => 4, 'culture_fit' => 4,
        ], 70.0);

        $this->assertSame(80.0, $r['normalized_score']);
        $this->assertTrue($r['passed']);
        $this->assertSame('yes', $r['recommendation_key']);
    }

    public function test_normalization_is_weight_scale_independent(): void
    {
        // Weights 3 + 2 = 5 (not 100); max 10. c1 full (3), c2 half (1) → 4/5 = 80%.
        $criteria = [
            ['key' => 'a', 'label' => 'A', 'weight' => 3, 'max' => 10],
            ['key' => 'b', 'label' => 'B', 'weight' => 2, 'max' => 10],
        ];
        $r = $this->engine()->score($criteria, ['a' => 10, 'b' => 5], 50.0);

        $this->assertSame(80.0, $r['normalized_score']);
        $this->assertSame(5.0, $r['max_score']);
        $this->assertSame(4.0, $r['overall_score']);
    }

    public function test_partial_scoring_only_counts_scored_criteria(): void
    {
        $criteria = [
            ['key' => 'a', 'label' => 'A', 'weight' => 50, 'max' => 5],
            ['key' => 'b', 'label' => 'B', 'weight' => 50, 'max' => 5],
        ];
        // Only 'a' is scored, at full marks → normalized over scored weight = 100.
        $r = $this->engine()->score($criteria, ['a' => 5], 70.0);

        $this->assertSame(1, $r['scored_count']);
        $this->assertSame(100.0, $r['normalized_score']);
    }

    public function test_scores_are_clamped_into_range(): void
    {
        $criteria = [['key' => 'a', 'label' => 'A', 'weight' => 100, 'max' => 5]];

        $high = $this->engine()->score($criteria, ['a' => 99], 50.0);
        $this->assertSame(100.0, $high['normalized_score']);

        $low = $this->engine()->score($criteria, ['a' => -3], 50.0);
        $this->assertSame(0.0, $low['normalized_score']);
        $this->assertFalse($low['passed']);
    }

    public function test_pass_threshold_is_inclusive(): void
    {
        $criteria = [['key' => 'a', 'label' => 'A', 'weight' => 100, 'max' => 5]];
        // 3.5/5 = 70% exactly.
        $r = $this->engine()->score($criteria, ['a' => 3.5], 70.0);

        $this->assertSame(70.0, $r['normalized_score']);
        $this->assertTrue($r['passed']);
    }

    public function test_recommendation_bands(): void
    {
        $e = $this->engine();
        $this->assertSame('strong_yes', $e->recommendationKey(85.0));
        $this->assertSame('yes', $e->recommendationKey(70.0));
        $this->assertSame('neutral', $e->recommendationKey(50.0));
        $this->assertSame('no', $e->recommendationKey(30.0));
        $this->assertSame('strong_no', $e->recommendationKey(0.0));
        $this->assertSame('strong_no', $e->recommendationKey(29.99));
    }

    public function test_empty_rubric_throws(): void
    {
        $threw = false;
        try {
            $this->engine()->score([], ['a' => 1], 50.0);
        } catch (\InvalidArgumentException) {
            $threw = true;
        }
        $this->assertTrue($threw);
    }

    public function test_factor_weighted_scores_sum_to_overall(): void
    {
        $r = $this->engine()->score($this->rubric(), [
            'technical_depth' => 4, 'problem_solving' => 3, 'system_design' => 5,
            'communication' => 2, 'culture_fit' => 4,
        ], 60.0);

        $sum = 0.0;
        foreach ($r['factors'] as $f) {
            $sum += (float) $f['weighted_score'];
        }
        $this->assertTrue(abs($sum - $r['overall_score']) < 0.001);
    }

    // --- Validation ---------------------------------------------------------

    public function test_validate_accepts_a_good_rubric(): void
    {
        $this->assertSame([], $this->template()->validateCriteria($this->rubric()));
    }

    public function test_validate_flags_duplicate_keys_and_bad_bounds(): void
    {
        $issues = $this->template()->validateCriteria([
            ['key' => 'a', 'label' => 'A', 'weight' => 1, 'max' => 5],
            ['key' => 'a', 'label' => 'A2', 'weight' => 1, 'max' => 0],
        ]);
        $this->assertTrue(count($issues) >= 2);
    }

    public function test_validate_flags_zero_total_weight(): void
    {
        $issues = $this->template()->validateCriteria([
            ['key' => 'a', 'label' => 'A', 'weight' => 0, 'max' => 5],
        ]);
        $this->assertTrue(in_array('Total criterion weight must be greater than zero.', $issues, true));
    }

    // --- Template instantiation + versioning (DB) ---------------------------

    public function test_instantiate_from_library_creates_form_and_fields(): void
    {
        $form = $this->template()->instantiateFromLibrary('backend_engineer');

        $this->assertSame('Backend Engineer', (string) $form->name);
        $fields = $form->fields();
        $this->assertSame(5, count($fields));
        $keys = array_map(static fn ($f): string => (string) $f['key'], $fields);
        $this->assertTrue(in_array('technical_depth', $keys, true));
    }

    public function test_unknown_library_key_throws(): void
    {
        $threw = false;
        try {
            $this->template()->instantiateFromLibrary('does_not_exist');
        } catch (RuntimeException) {
            $threw = true;
        }
        $this->assertTrue($threw);
    }

    public function test_publish_snapshots_and_supersedes_prior_version(): void
    {
        $tpl = $this->template();
        $form = $tpl->instantiateFromLibrary('sales_representative');
        $formId = (int) $form->getKey();

        $v1 = $tpl->publish($formId, null, 'first');
        $this->assertSame(1, (int) $v1->version);
        $this->assertTrue((bool) $v1->is_active);

        $snapshot = $tpl->activeSnapshot($formId);
        $this->assertNotNull($snapshot);
        $this->assertSame(5, count($snapshot['criteria']));

        // Publishing again supersedes v1.
        $v2 = $tpl->publish($formId, null, 'second');
        $this->assertSame(2, (int) $v2->version);

        $active = EvaluationFormVersion::activeFor($formId);
        $this->assertSame(2, (int) $active->version);

        $total = EvaluationFormVersion::query()->where('form_id', '=', $formId)->count();
        $this->assertSame(2, $total);
    }

    // --- Decision persistence (DB) ------------------------------------------

    public function test_decide_persists_record_and_explainable_factors(): void
    {
        $criteria = $this->rubric();
        $record = $this->engine()->decide($criteria, [
            'technical_depth' => 5, 'problem_solving' => 5, 'system_design' => 5,
            'communication' => 5, 'culture_fit' => 5,
        ], 70.0, ['is_ai' => true, 'summary' => 'Strong candidate']);

        $this->assertSame(100.0, (float) $record->normalized_score);
        $this->assertTrue((bool) $record->passed);
        $this->assertTrue((bool) $record->is_ai);
        $this->assertSame(lookup_id('recommendation', 'strong_yes'), (int) $record->recommendation_id);

        $factors = $record->factors();
        $this->assertSame(5, count($factors));

        $sum = 0.0;
        foreach ($factors as $f) {
            $sum += (float) $f['weighted_score'];
        }
        $this->assertTrue(abs($sum - (float) $record->overall_score) < 0.001);
    }
};
