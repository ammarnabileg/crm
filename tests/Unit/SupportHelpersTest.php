<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit;

use HaHireAI\Support\Filename;
use HaHireAI\Support\Slug;
use HaHireAI\Support\TemplateTokens;
use PHPUnit\Framework\TestCase;

/**
 * The shared Support helpers extracted from previously-duplicated logic
 * (docs/REQUIRES_APPROVAL.md §4). These lock in the exact behaviour the old
 * per-service copies produced, so the consolidation is provably behaviour-preserving.
 */
final class SupportHelpersTest extends TestCase
{
    public function test_slug_lowercases_collapses_and_trims(): void
    {
        $this->assertSame('senior-php-developer', Slug::make('  Senior   PHP  Developer!! '));
        $this->assertSame('react-english', Slug::make('React / English'));
        $this->assertSame('', Slug::make('   ***   '));   // no alphanumerics → empty (caller adds fallback)
        $this->assertSame('abc', Slug::make('abc', 3));
        $this->assertSame('abc', Slug::make('abcdef', 3)); // maxLength truncates
    }

    public function test_slug_matches_the_legacy_job_slug_base(): void
    {
        // Old JobService: trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($t)), '-') ?: 'job'
        foreach (['Backend Engineer', 'Data/ML Lead', '   ', 'C++ Guru'] as $title) {
            $legacy = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-');
            $this->assertSame($legacy, Slug::make($title), "slug drift for [{$title}]");
        }
    }

    public function test_filename_sanitises_and_falls_back(): void
    {
        $this->assertSame('my_cv.pdf', Filename::safe('my cv.pdf', 'file'));
        $this->assertSame('a-b_c.docx', Filename::safe('a-b c.docx', 'file'));
        $this->assertSame('file', Filename::safe('///', 'file'));   // nothing valid → fallback
        $this->assertSame('cv', Filename::safe('', 'cv'));
        $this->assertSame(120, strlen(Filename::safe(str_repeat('a', 300), 'file'))); // capped at 120
    }

    public function test_template_tokens_resolve_scalars_and_json(): void
    {
        $this->assertSame('Hello Ada', TemplateTokens::resolve('Hello {{name}}', ['name' => 'Ada']));
        $this->assertSame('no tokens here', TemplateTokens::resolve('no tokens here', ['name' => 'Ada']));
        $this->assertSame('', TemplateTokens::resolve('{{missing}}', []));               // missing → empty
        $this->assertSame('[1,2]', TemplateTokens::resolve('{{list}}', ['list' => [1, 2]])); // non-scalar → JSON
        $this->assertSame('a.b works', TemplateTokens::resolve('{{a.b}} works', ['a.b' => 'a.b'])); // dotted keys
    }
}
