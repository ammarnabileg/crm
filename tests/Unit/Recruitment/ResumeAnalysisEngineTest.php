<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Recruitment;

use HaHireAI\Modules\Recruitment\Domain\FirstImpression\JobProfile;
use HaHireAI\Modules\Recruitment\Domain\FirstImpression\ResumeAnalysisEngine;
use HaHireAI\Modules\Recruitment\Domain\Resume\ExtractedText;
use HaHireAI\Modules\Recruitment\Domain\Resume\ResumeStructurer;
use PHPUnit\Framework\TestCase;

/** Engine 1 — the deterministic résumé/job-fit scorer (no AI). */
final class ResumeAnalysisEngineTest extends TestCase
{
    private ResumeAnalysisEngine $engine;

    private function resume(string $cv): \HaHireAI\Modules\Recruitment\Domain\Resume\ParsedResume
    {
        return (new ResumeStructurer())->structure(new ExtractedText($cv, 100, 'txt'));
    }

    protected function setUp(): void
    {
        $this->engine = new ResumeAnalysisEngine();
    }

    private const PHP_CV = "Sara Hassan\nSenior Software Engineer\nsara@example.com\n"
        . "Summary\n8+ years building PHP and Laravel platforms.\n"
        . "Experience\nSenior Software Engineer at Acme (2018 - Present)\n"
        . "Skills\nPHP, Laravel, MySQL, Docker, REST, JavaScript\n"
        . "Education\nBSc Computer Science\nLanguages\nArabic, English";

    public function test_strong_match_scores_high_and_lists_matched_skills(): void
    {
        $job = JobProfile::fromJobRow([
            'title' => 'Senior PHP Engineer', 'seniority' => 'senior',
            'required_skills' => 'PHP,Laravel,MySQL,Docker', 'screening_keywords' => 'php,laravel',
            'experience_min' => 5,
        ]);
        $r = $this->engine->analyze($this->resume(self::PHP_CV), $job);

        $this->assertGreaterThanOrEqual(80, $r->resumeScore);
        $this->assertSame(100, $r->subScore('skill_match'));
        $this->assertSame(100, $r->subScore('experience_match'));
        $this->assertEqualsCanonicalizing(['PHP', 'Laravel', 'MySQL', 'Docker'], $r->matchedSkills);
        $this->assertSame([], $r->missingSkills);
    }

    public function test_mismatched_role_scores_low_and_lists_missing_skills(): void
    {
        $job = JobProfile::fromJobRow([
            'title' => 'Senior Java Engineer', 'seniority' => 'lead',
            'required_skills' => 'Java,Spring,Kotlin,Kafka', 'screening_keywords' => 'java,spring',
            'experience_min' => 8,
        ]);
        $r = $this->engine->analyze($this->resume(self::PHP_CV), $job);

        $this->assertSame(0, $r->subScore('skill_match'));
        $this->assertLessThan($r->qualityScore, $r->jobMatchScore, 'job relevance should drag the score down');
        $this->assertEqualsCanonicalizing(['Java', 'Spring', 'Kotlin', 'Kafka'], $r->missingSkills);
        $this->assertNotEmpty($r->weaknesses);
    }

    public function test_resume_score_blends_relevance_and_quality(): void
    {
        $job = JobProfile::fromJobRow(['title' => 'PHP Engineer', 'required_skills' => 'PHP,Laravel,MySQL']);
        $r = $this->engine->analyze($this->resume(self::PHP_CV), $job);
        $expected = (int) round(0.70 * $r->jobMatchScore + 0.30 * $r->qualityScore);
        $this->assertSame($expected, $r->resumeScore);
    }

    public function test_no_required_skills_is_not_punished(): void
    {
        $job = JobProfile::fromJobRow(['title' => 'Engineer']);
        $r = $this->engine->analyze($this->resume(self::PHP_CV), $job);
        $this->assertGreaterThanOrEqual(60, $r->subScore('skill_match'));
    }

    public function test_all_subscores_are_bounded(): void
    {
        $job = JobProfile::fromJobRow(['title' => 'PHP Engineer', 'required_skills' => 'PHP', 'experience_min' => 3]);
        $r = $this->engine->analyze($this->resume(self::PHP_CV), $job);
        foreach ($r->subScores as $key => $v) {
            $this->assertGreaterThanOrEqual(0, $v, $key);
            $this->assertLessThanOrEqual(100, $v, $key);
        }
        $this->assertGreaterThanOrEqual(0, $r->confidence);
        $this->assertLessThanOrEqual(100, $r->confidence);
    }
}
