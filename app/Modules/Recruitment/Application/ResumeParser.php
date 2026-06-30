<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Modules\Recruitment\Domain\Resume\ExtractedText;
use HaHireAI\Modules\Recruitment\Domain\Resume\ResumeStructurer;

/**
 * A deterministic, dependency-free résumé/CV text parser that returns a FLAT set
 * of structured fields (contact, links, experience, skills) so a recruiter can
 * pre-fill a candidate's structured profile from pasted CV text.
 *
 * This is a thin BACKWARD-COMPATIBLE adapter over the canonical Resume Parsing
 * Layer ({@see ResumeStructurer} + the ontology). It used to carry its own regex
 * and a hard-coded skill list; that logic was byte-for-byte duplicated by the
 * parsing layer the First Impression Engine introduced, so it now delegates and
 * projects the rich {@see \HaHireAI\Modules\Recruitment\Domain\Resume\ParsedResume}
 * down to the legacy flat shape. Same public contract, one source of truth.
 */
final class ResumeParser
{
    private readonly ResumeStructurer $structurer;

    public function __construct(?ResumeStructurer $structurer = null)
    {
        $this->structurer = $structurer ?? new ResumeStructurer();
    }

    /**
     * @return array{
     *   email?: string, phone?: string, years_experience?: int,
     *   skills?: list<string>, linkedin?: string, github?: string, links?: list<string>
     * }
     */
    public function parse(string $text): array
    {
        $resume = $this->structurer->structure(new ExtractedText($text, 100, 'text'));

        $out = [];
        if ($resume->email !== null) {
            $out['email'] = $resume->email;
        }
        if ($resume->phone !== null) {
            $out['phone'] = $resume->phone;
        }
        if ($resume->yearsExperience !== null) {
            $out['years_experience'] = $resume->yearsExperience;
        }
        if ($resume->skills !== []) {
            $out['skills'] = $resume->skills;
        }

        // Split the detected links into the legacy linkedin/github/other buckets.
        $other = [];
        foreach ($resume->links as $url) {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if (str_contains($host, 'linkedin.') && ! isset($out['linkedin'])) {
                $out['linkedin'] = $url;
            } elseif (str_contains($host, 'github.') && ! isset($out['github'])) {
                $out['github'] = $url;
            } else {
                $other[$url] = true;
            }
        }
        if ($other !== []) {
            $out['links'] = array_values(array_slice(array_keys($other), 0, 8));
        }

        return $out;
    }
}
