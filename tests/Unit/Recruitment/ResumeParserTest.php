<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Recruitment;

use HaHireAI\Modules\Recruitment\Application\ResumeParser;
use PHPUnit\Framework\TestCase;

/** Sprint (CV parser) — deterministic extraction of structured fields from CV text. */
final class ResumeParserTest extends TestCase
{
    private ResumeParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ResumeParser();
    }

    public function test_extracts_contact_links_experience_and_skills(): void
    {
        $cv = implode("\n", [
            'Sara Hassan — Senior Software Engineer',
            'Email: Sara.Hassan@example.com  ·  Phone: +20 100 123 4567',
            'LinkedIn: https://www.linkedin.com/in/sarahassan',
            'GitHub: https://github.com/sarah',
            'Portfolio: https://sarah.dev',
            '',
            'Summary: 8+ years of experience building web platforms.',
            'Skills: PHP, Laravel, MySQL, React, TypeScript, Docker, AWS, Agile.',
        ]);

        $r = $this->parser->parse($cv);

        $this->assertSame('sara.hassan@example.com', $r['email']);
        $this->assertArrayHasKey('phone', $r);
        $this->assertSame(8, $r['years_experience']);
        $this->assertSame('https://www.linkedin.com/in/sarahassan', $r['linkedin']);
        $this->assertSame('https://github.com/sarah', $r['github']);
        $this->assertContains('https://sarah.dev', $r['links']);

        foreach (['PHP', 'Laravel', 'MySQL', 'React', 'TypeScript', 'Docker', 'AWS', 'Agile'] as $skill) {
            $this->assertContains($skill, $r['skills'], "expected skill {$skill}");
        }
    }

    public function test_skill_matching_respects_word_boundaries(): void
    {
        // "Java" must NOT be reported just because "JavaScript" appears.
        $r = $this->parser->parse('Proficient in JavaScript and TypeScript.');
        $this->assertContains('JavaScript', $r['skills']);
        $this->assertNotContains('Java', $r['skills']);
    }

    public function test_picks_the_largest_plausible_experience(): void
    {
        $r = $this->parser->parse('2 years at A, then 6 years at B. Total 8 yrs.');
        $this->assertSame(8, $r['years_experience']);
    }

    public function test_returns_empty_for_text_without_structured_fields(): void
    {
        $r = $this->parser->parse('Just a paragraph of prose with nothing structured to extract here.');
        $this->assertArrayNotHasKey('email', $r);
        $this->assertArrayNotHasKey('years_experience', $r);
        $this->assertArrayNotHasKey('skills', $r);
    }
}
