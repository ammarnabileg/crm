<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Recruitment;

use HaHireAI\Modules\Recruitment\Domain\FirstImpression\CandidateInsights;
use HaHireAI\Modules\Recruitment\Domain\FirstImpression\JobProfile;
use HaHireAI\Modules\Recruitment\Domain\Resume\ExtractedText;
use HaHireAI\Modules\Recruitment\Domain\Resume\ResumeStructurer;
use HaHireAI\Modules\Recruitment\Domain\SkillOntology;
use PHPUnit\Framework\TestCase;

/**
 * Candidate Intelligence — the zero-AI advisory enrichment. Pure, deterministic;
 * it must never throw and must produce explainable structured insights.
 */
final class CandidateInsightsTest extends TestCase
{
    private function resume(string $text): \HaHireAI\Modules\Recruitment\Domain\Resume\ParsedResume
    {
        return (new ResumeStructurer())->structure(new ExtractedText($text, 100, 'text'));
    }

    private function job(string $title, string $skills): JobProfile
    {
        return JobProfile::fromJobRow(['title' => $title, 'required_skills' => $skills, 'seniority' => 'senior']);
    }

    public function test_detects_upward_career_progression(): void
    {
        $cv = implode("\n", [
            'Mona Adel',
            'Experience',
            'Engineering Manager at Acme (2022 - Present)',
            'Senior Software Engineer at Beta (2018 - 2022)',
            'Junior Developer at Gamma (2016 - 2018)',
        ]);
        $out = CandidateInsights::derive($this->resume($cv), $this->job('Engineering Manager', 'PHP'));

        $this->assertContains($out['career_progression']['trajectory'], ['upward', 'mostly_upward']);
        $this->assertGreaterThan(0, $out['seniority']['rank']);
    }

    public function test_detects_leadership_industry_and_stack(): void
    {
        $cv = implode("\n", [
            'Sara Hassan',
            'Summary',
            'Led a team of 6 engineers at a fintech bank, managed the payments platform.',
            'Skills',
            'PHP, Laravel, React, TypeScript, AWS, Docker, MySQL',
        ]);
        $out = CandidateInsights::derive($this->resume($cv), $this->job('Backend Engineer', 'PHP,Laravel'));

        $this->assertNotEmpty($out['leadership']);
        $this->assertContains('Fintech / Banking', $out['industries']);
        $this->assertArrayHasKey('Backend', $out['tech_stacks']);
        $this->assertNotNull($out['primary_stack']);
    }

    public function test_skill_gap_analysis_marks_critical_when_in_title(): void
    {
        $cv = "Jane Doe\nSkills\nPHP, MySQL"; // no Kubernetes
        $out = CandidateInsights::derive($this->resume($cv), $this->job('Kubernetes Platform Engineer', 'Kubernetes,PHP'));

        $gapSkills = array_column($out['skill_gaps'], 'skill');
        $this->assertContains('Kubernetes', $gapSkills);
        $critical = array_filter($out['skill_gaps'], static fn (array $g): bool => $g['skill'] === 'Kubernetes');
        $this->assertSame('critical', array_values($critical)[0]['severity']);
    }

    public function test_consistency_flags_year_mismatch(): void
    {
        $cv = implode("\n", [
            'Ali',
            'Summary',
            '20 years of experience.',
            'Experience',
            'Developer at X (2021 - 2023)',
        ]);
        $out = CandidateInsights::derive($this->resume($cv), $this->job('Developer', 'PHP'));
        $this->assertNotEmpty($out['consistency']);
    }

    public function test_portfolio_and_resume_quality(): void
    {
        $cv = implode("\n", [
            'Sara Hassan',
            'sara@example.com  ·  +20 100 123 4567',
            'GitHub: https://github.com/sara',
            'Portfolio: https://sara.dev',
            'Summary',
            'Experienced engineer.',
            'Skills',
            'PHP, Laravel',
        ]);
        $out = CandidateInsights::derive($this->resume($cv), $this->job('Engineer', 'PHP'));

        $this->assertContains('GitHub', $out['portfolio']['sources']);
        $this->assertGreaterThan(0, $out['portfolio']['score']);
        $this->assertGreaterThan(0, $out['resume_quality']['score']);
        $this->assertNotSame('', $out['resume_quality']['label']);
    }

    public function test_never_throws_on_empty_resume(): void
    {
        $out = CandidateInsights::derive($this->resume(''), $this->job('Anything', ''));
        $this->assertSame('insufficient', $out['career_progression']['trajectory']);
        $this->assertSame([], $out['leadership']);
        $this->assertSame([], $out['skill_gaps']);
    }

    public function test_ontology_stack_grouping(): void
    {
        $stacks = SkillOntology::detectStacks(['React', 'Vue.js', 'PHP', 'AWS', 'Docker']);
        $this->assertArrayHasKey('Frontend', $stacks);
        $this->assertArrayHasKey('Backend', $stacks);
        $this->assertArrayHasKey('DevOps/Cloud', $stacks);
        $this->assertContains('React', $stacks['Frontend']);
    }
}
