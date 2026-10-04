<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Recruitment;

use HaHireAI\Modules\Recruitment\Domain\SkillOntology;
use PHPUnit\Framework\TestCase;

/** The alias-aware matching brain behind the no-AI engines. */
final class SkillOntologyTest extends TestCase
{
    public function test_detects_skills_through_aliases(): void
    {
        $text = 'Built APIs with JS, k8s, postgres and node.';
        $found = SkillOntology::detectSkills($text);
        $this->assertContains('JavaScript', $found);
        $this->assertContains('Kubernetes', $found);
        $this->assertContains('PostgreSQL', $found);
        $this->assertContains('Node.js', $found);
    }

    public function test_canonical_normalises_aliases(): void
    {
        $this->assertSame('JavaScript', SkillOntology::canonical('js'));
        $this->assertSame('Kubernetes', SkillOntology::canonical('K8S'));
        $this->assertSame('PostgreSQL', SkillOntology::canonical('postgres'));
        // Unknown terms pass through trimmed.
        $this->assertSame('Underwater Basket Weaving', SkillOntology::canonical('  Underwater Basket Weaving '));
    }

    public function test_skill_present_handles_symbol_heavy_names(): void
    {
        $this->assertTrue(SkillOntology::skillPresent('C#', 'Strong C# and .NET background'));
        $this->assertTrue(SkillOntology::skillPresent('.NET', 'Strong C# and .NET background'));
        $this->assertTrue(SkillOntology::skillPresent('C++', 'Systems work in C++'));
        // "Java" must not match inside "JavaScript".
        $this->assertFalse(SkillOntology::skillPresent('Java', 'Only JavaScript here'));
    }

    public function test_word_boundaries_avoid_false_positives(): void
    {
        // "Go" should not match "Google" or "going".
        $this->assertFalse(SkillOntology::skillPresent('Go', 'Going to Google later'));
        $this->assertTrue(SkillOntology::skillPresent('Go', 'Backend in Go and Rust'));
    }

    public function test_infer_seniority_picks_strongest_cue(): void
    {
        $this->assertSame(5, SkillOntology::inferSeniority('Senior Software Engineer'));
        $this->assertSame(6, SkillOntology::inferSeniority('Tech Lead and Senior Engineer'));
        $this->assertSame(2, SkillOntology::inferSeniority('Junior Developer'));
        $this->assertSame(0, SkillOntology::inferSeniority('Software Developer'));
    }

    public function test_detect_education_level_takes_the_highest(): void
    {
        $this->assertSame('master', SkillOntology::detectEducationLevel('BSc then MSc in CS'));
        $this->assertSame('doctorate', SkillOntology::detectEducationLevel('PhD in Machine Learning'));
        $this->assertNull(SkillOntology::detectEducationLevel('No formal study listed'));
    }

    public function test_detect_languages(): void
    {
        $found = SkillOntology::detectLanguages('Fluent in Arabic and English, basic French');
        $this->assertContains('Arabic', $found);
        $this->assertContains('English', $found);
        $this->assertContains('French', $found);
    }

    public function test_job_seniority_rank_maps_enum(): void
    {
        $this->assertSame(5, SkillOntology::jobSeniorityRank('senior'));
        $this->assertSame(2, SkillOntology::jobSeniorityRank('junior'));
        $this->assertSame(0, SkillOntology::jobSeniorityRank(null));
        $this->assertSame(0, SkillOntology::jobSeniorityRank('nonsense'));
    }
}
