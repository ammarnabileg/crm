<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Recruitment;

use HaHireAI\Modules\Recruitment\Domain\CvScreening;
use PHPUnit\Framework\TestCase;

/**
 * The deterministic, no-AI keyword pre-screen that gates the paid AI interview.
 */
final class CvScreeningTest extends TestCase
{
    public function test_keywords_are_split_trimmed_lowercased_and_deduped(): void
    {
        $this->assertSame(['php', 'mysql', 'rest api'], CvScreening::keywords("PHP, MySQL\n REST API ,php"));
        $this->assertSame([], CvScreening::keywords(''));
        $this->assertSame([], CvScreening::keywords(null));
    }

    public function test_hits_finds_case_insensitive_matches_without_duplicates(): void
    {
        $text = 'Senior PHP engineer, built REST APIs on MySQL.';
        $this->assertSame(['php', 'mysql'], CvScreening::hits($text, ['PHP', 'MySQL', 'golang']));
        $this->assertSame([], CvScreening::hits($text, ['golang', 'rust']));
    }

    public function test_passes_is_open_when_no_keywords_are_defined(): void
    {
        $this->assertTrue(CvScreening::passes('anything at all', []));
    }

    public function test_passes_requires_at_least_one_keyword_by_default(): void
    {
        $this->assertTrue(CvScreening::passes('I work with PHP daily', ['php', 'go']));
        $this->assertFalse(CvScreening::passes('I work with Java daily', ['php', 'go']));
    }

    public function test_passes_honours_a_higher_minimum_hit_count(): void
    {
        $text = 'PHP and MySQL, plus some REST API work';
        $this->assertTrue(CvScreening::passes($text, ['php', 'mysql', 'docker'], 2));
        $this->assertFalse(CvScreening::passes($text, ['php', 'docker', 'kubernetes'], 2));
    }
}
