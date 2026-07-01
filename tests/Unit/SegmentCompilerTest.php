<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit;

use HaHireAI\Modules\Recruitment\Domain\TalentSegment\SegmentCompiler;
use HaHireAI\Modules\Recruitment\Domain\TalentSegment\SegmentField;
use HaHireAI\Modules\Recruitment\Domain\TalentSegment\SegmentRule;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** The Smart-Segment rule compiler is a pure function of rules + match_type + now. */
final class SegmentCompilerTest extends TestCase
{
    private SegmentCompiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new SegmentCompiler();
    }

    public function test_match_type_selects_the_glue(): void
    {
        $rules = [new SegmentRule(SegmentField::SKILL, 'React')];

        $this->assertSame(' AND ', $this->compiler->compile($rules, 'all', '2026-07-01 00:00:00')['glue']);
        $this->assertSame(' OR ', $this->compiler->compile($rules, 'any', '2026-07-01 00:00:00')['glue']);
        // Unknown/blank match types fall back to AND (the safe default).
        $this->assertSame(' AND ', $this->compiler->compile($rules, 'nonsense', '2026-07-01 00:00:00')['glue']);
    }

    public function test_skill_rule_matches_normalised_field_with_json_fallback(): void
    {
        $out = $this->compiler->compile([new SegmentRule(SegmentField::SKILL, 'React')], 'all', '2026-07-01 00:00:00');
        $p = $out['predicates'][0];

        $this->assertStringContainsString('candidate_profile_fields', $p['sql']);
        $this->assertStringContainsString("f.field_key = 'skills'", $p['sql']);
        $this->assertStringContainsString('CAST(cp.details AS CHAR) LIKE ?', $p['sql']);
        $this->assertSame(['%React%', '%React%'], $p['bindings']); // one per LIKE placeholder, in order
        $this->assertSame('Skill: React', $p['label']);
    }

    public function test_language_and_seniority_target_their_field_keys(): void
    {
        $lang = $this->compiler->compile([new SegmentRule(SegmentField::LANGUAGE, 'English')], 'all', '2026-07-01 00:00:00')['predicates'][0];
        $this->assertStringContainsString("f.field_key = 'languages'", $lang['sql']);
        $this->assertSame('Language: English', $lang['label']);

        $sen = $this->compiler->compile([new SegmentRule(SegmentField::SENIORITY, 'senior')], 'all', '2026-07-01 00:00:00')['predicates'][0];
        $this->assertStringContainsString("f.field_key = 'seniority'", $sen['sql']);
        $this->assertSame('Seniority: senior', $sen['label']);
    }

    public function test_min_score_uses_max_fit_score_and_casts_to_int(): void
    {
        $p = $this->compiler->compile([new SegmentRule(SegmentField::MIN_SCORE, '85')], 'all', '2026-07-01 00:00:00')['predicates'][0];

        $this->assertStringContainsString('MAX(ca.fit_score)', $p['sql']);
        $this->assertStringContainsString('>= ?', $p['sql']);
        $this->assertSame(['85'], $p['bindings']);
        $this->assertSame('Score ≥ 85', $p['label']);
    }

    public function test_last_interview_computes_threshold_from_injected_now(): void
    {
        $p = $this->compiler->compile(
            [new SegmentRule(SegmentField::LAST_INTERVIEW_MONTHS, '6')],
            'all',
            '2026-07-01 00:00:00',
        )['predicates'][0];

        $this->assertStringContainsString("i.status = 'completed'", $p['sql']);
        $this->assertStringContainsString('COALESCE(i.completed_at, i.created_at) >= ?', $p['sql']);
        $this->assertSame(['2026-01-01 00:00:00'], $p['bindings']); // 2026-07-01 minus 6 months
        $this->assertSame('Interviewed ≤ 6 months ago', $p['label']);
    }

    public function test_available_needs_no_value_and_no_bindings(): void
    {
        $p = $this->compiler->compile([new SegmentRule(SegmentField::AVAILABLE, '')], 'all', '2026-07-01 00:00:00')['predicates'][0];

        $this->assertSame([], $p['bindings']);
        $this->assertStringContainsString("f.field_key IN ('availability', 'available')", $p['sql']);
        $this->assertSame('Available', $p['label']);
    }

    public function test_status_rule_binds_the_value(): void
    {
        $p = $this->compiler->compile([new SegmentRule(SegmentField::STATUS, 'hired')], 'all', '2026-07-01 00:00:00')['predicates'][0];

        $this->assertStringContainsString('a.status = ?', $p['sql']);
        $this->assertSame(['hired'], $p['bindings']);
        $this->assertSame('Status: hired', $p['label']);
    }

    public function test_predicates_preserve_rule_order(): void
    {
        $out = $this->compiler->compile([
            new SegmentRule(SegmentField::SKILL, 'React'),
            new SegmentRule(SegmentField::LANGUAGE, 'English'),
            new SegmentRule(SegmentField::MIN_SCORE, '85'),
        ], 'all', '2026-07-01 00:00:00');

        $this->assertCount(3, $out['predicates']);
        $this->assertSame('Skill: React', $out['predicates'][0]['label']);
        $this->assertSame('Language: English', $out['predicates'][1]['label']);
        $this->assertSame('Score ≥ 85', $out['predicates'][2]['label']);
    }

    public function test_invalid_field_is_rejected_at_the_rule_boundary(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SegmentRule('not_a_field', 'x');
    }

    public function test_value_required_fields_reject_blank_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SegmentRule(SegmentField::SKILL, '   ');
    }
}
