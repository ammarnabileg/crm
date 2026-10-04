<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Domain\Resume;

use HaHireAI\Modules\Recruitment\Domain\SkillOntology;

/**
 * Turns normalised résumé TEXT into a structured {@see ParsedResume}. This is the
 * "extract & structure" half of the parsing layer — fully format-agnostic (it
 * never sees a file). Every field is best-effort: if a field can't be found it
 * stays empty and the Analysis Engine falls back to the raw text
 * (docs/FIRST_IMPRESSION_ENGINE.md §2). Pure, deterministic, unit-testable.
 */
final class ResumeStructurer
{
    private const SECTION_HEADINGS = [
        'summary' => ['summary', 'profile', 'objective', 'about me', 'about', 'professional summary'],
        'experience' => ['experience', 'work experience', 'employment', 'work history', 'professional experience', 'career history'],
        'education' => ['education', 'academic background', 'academic qualifications', 'qualifications'],
        'skills' => ['skills', 'technical skills', 'core competencies', 'competencies', 'technologies'],
        'certifications' => ['certifications', 'certificates', 'licenses', 'licences'],
        'languages' => ['languages', 'language'],
        'projects' => ['projects', 'personal projects', 'selected projects'],
        'publications' => ['publications', 'papers', 'research'],
        'awards' => ['awards', 'honors', 'honours', 'achievements'],
    ];

    public function structure(ExtractedText $extracted): ParsedResume
    {
        $text = $extracted->text;
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), static fn (string $l): bool => $l !== ''));
        $sections = $this->splitSections($lines);

        $email = $this->firstMatch('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $text);
        $links = $this->extractLinks($text);
        $experiences = $this->extractExperiences($sections['experience'] ?? []);

        return new ParsedResume(
            rawText: $text,
            parseConfidence: $extracted->confidence,
            parser: $extracted->parser,
            name: $this->extractName($lines, $email),
            email: $email !== null ? strtolower($email) : null,
            phone: $this->extractPhone($text),
            address: $this->extractAddress($lines),
            summary: $this->extractSummary($sections),
            yearsExperience: $this->extractYears($text, $experiences),
            links: $links,
            jobTitles: $this->extractTitles($experiences, $sections['experience'] ?? []),
            companies: $this->extractCompanies($experiences),
            experiences: $experiences,
            skills: $this->extractSkills($text, $sections['skills'] ?? []),
            education: $this->extractList($sections['education'] ?? [], 6),
            certifications: $this->extractCertifications($text, $sections['certifications'] ?? []),
            languages: $this->extractLanguages($text, $sections['languages'] ?? []),
            projects: $this->extractList($sections['projects'] ?? [], 8),
            publications: $this->extractList($sections['publications'] ?? [], 8),
            awards: $this->extractList($sections['awards'] ?? [], 8),
        );
    }

    /**
     * Slice the résumé into its labelled sections by detecting heading lines.
     *
     * @param  list<string>  $lines
     * @return array<string, list<string>>
     */
    private function splitSections(array $lines): array
    {
        $sections = [];
        $current = 'header';
        foreach ($lines as $line) {
            $heading = $this->headingFor($line);
            if ($heading !== null) {
                $current = $heading;
                $sections[$current] ??= [];

                continue;
            }
            $sections[$current][] = $line;
        }

        return $sections;
    }

    private function headingFor(string $line): ?string
    {
        $clean = mb_strtolower(trim($line, " \t:#-—•·"));
        if ($clean === '' || mb_strlen($clean) > 32) {
            return null; // headings are short
        }
        foreach (self::SECTION_HEADINGS as $key => $names) {
            if (in_array($clean, $names, true)) {
                return $key;
            }
        }

        return null;
    }

    /** @param list<string> $lines */
    private function extractName(array $lines, ?string $email): ?string
    {
        foreach (array_slice($lines, 0, 5) as $line) {
            $candidate = trim($line);
            // A name line: 2–4 words, mostly letters, no @/digits/url.
            if (preg_match('/@|https?:|\d/', $candidate)) {
                continue;
            }
            $words = preg_split('/\s+/', $candidate) ?: [];
            $wordCount = count($words);
            if ($wordCount >= 2 && $wordCount <= 4 && mb_strlen($candidate) <= 48
                && preg_match('/^[\p{L}.\'\- ]+$/u', $candidate)) {
                return $candidate;
            }
        }

        // Fall back to the local part of the email (e.g. "sara.hassan" → "Sara Hassan").
        if ($email !== null && str_contains($email, '@')) {
            $local = str_replace(['.', '_', '-'], ' ', explode('@', $email)[0]);
            if (preg_match('/^[a-z ]{3,}$/i', $local)) {
                return ucwords(trim($local));
            }
        }

        return null;
    }

    private function extractPhone(string $text): ?string
    {
        if (! preg_match_all('/\+?\d[\d\s().\-]{7,}\d/', $text, $m)) {
            return null;
        }
        foreach ($m[0] as $candidate) {
            $digits = preg_replace('/\D+/', '', $candidate) ?? '';
            $len = strlen($digits);
            if ($len >= 8 && $len <= 15) {
                return trim($candidate);
            }
        }

        return null;
    }

    /** @param list<string> $lines */
    private function extractAddress(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/^\s*(address|location|based in)\s*[:\-]\s*(.+)$/i', $line, $m)) {
                return trim($m[2]);
            }
        }
        // A line that looks like "City, Country".
        foreach (array_slice($lines, 0, 12) as $line) {
            if (preg_match('/^[\p{L} .\-]{2,40},\s*[\p{L} .\-]{2,40}$/u', trim($line))
                && ! str_contains(mb_strtolower($line), 'university')) {
                return trim($line);
            }
        }

        return null;
    }

    /** @param array<string, list<string>> $sections */
    private function extractSummary(array $sections): ?string
    {
        $lines = $sections['summary'] ?? [];
        if ($lines !== []) {
            return trim(mb_substr(implode(' ', array_slice($lines, 0, 6)), 0, 600));
        }
        // Otherwise the first substantial header paragraph.
        foreach ($sections['header'] ?? [] as $line) {
            if (mb_strlen($line) >= 60 && ! preg_match('/@|https?:/', $line)) {
                return trim(mb_substr($line, 0, 600));
            }
        }

        return null;
    }

    /** @return list<string> */
    private function extractLinks(string $text): array
    {
        $links = [];
        if (preg_match_all('#https?://[^\s)\]<>"]+#i', $text, $m)) {
            foreach ($m[0] as $url) {
                $clean = rtrim($url, '.,);:');
                $links[$clean] = true;
            }
        }
        // Bare domains for the common profile hosts (e.g. "github.com/sara").
        if (preg_match_all('#\b(?:github|linkedin|behance|dribbble|gitlab|medium|kaggle|stackoverflow)\.com/[^\s)\]<>",]+#i', $text, $m)) {
            foreach ($m[0] as $url) {
                $links['https://' . rtrim($url, '.,);:')] = true;
            }
        }

        return array_values(array_slice(array_keys($links), 0, 20));
    }

    /**
     * @param  list<string>  $expLines
     * @return list<array{title?: string, company?: string, start?: ?string, end?: ?string, months?: ?int}>
     */
    private function extractExperiences(array $expLines): array
    {
        $out = [];
        foreach ($expLines as $line) {
            $range = $this->parseDateRange($line);
            // "Title at Company" or "Title — Company" or "Title, Company"
            $titleCompany = null;
            if (preg_match('/^(.{2,60}?)\s+(?:at|@|—|–|-|,|\|)\s+(.{2,60}?)(?:\s*[\(\|].*)?$/u', $line, $m)) {
                $titleCompany = [trim($m[1]), trim($m[2])];
            }

            if ($range === null && $titleCompany === null) {
                continue;
            }
            $entry = [];
            if ($titleCompany !== null) {
                $entry['title'] = preg_replace('/\s*[\d–\-\(].*$/u', '', $titleCompany[0]) ?: $titleCompany[0];
                $entry['company'] = preg_replace('/\s*[\(\|].*$/u', '', $titleCompany[1]) ?: $titleCompany[1];
            }
            if ($range !== null) {
                $entry['start'] = $range['start'];
                $entry['end'] = $range['end'];
                $entry['months'] = $range['months'];
            }
            if ($entry !== []) {
                $out[] = $entry;
            }
            if (count($out) >= 20) {
                break;
            }
        }

        return $out;
    }

    /**
     * Parse a date range like "Jan 2019 - Present", "2018 – 2022", "03/2019-06/2021".
     *
     * @return array{start: ?string, end: ?string, months: ?int}|null
     */
    private function parseDateRange(string $line): ?array
    {
        $sep = '\s*(?:-|–|—|to|until|\bto\b)\s*';
        $token = '((?:\d{1,2}[\/\.])?(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec[a-z]*\.?\s*)?\d{4}|present|current|now)';
        if (! preg_match('/' . $token . $sep . $token . '/i', $line, $m)) {
            return null;
        }
        $start = $this->toYearMonth($m[1]);
        $end = preg_match('/present|current|now/i', $m[2]) ? null : $this->toYearMonth($m[2]);
        $months = null;
        if ($start !== null) {
            [$sy, $sm] = $start;
            if ($end !== null) {
                [$ey, $em] = $end;
            } else {
                // "Present" — estimate against a recent fixed anchor (no clock in pure code).
                $ey = max($sy, 2026);
                $em = 1;
            }
            $months = max(0, ($ey - $sy) * 12 + ($em - $sm));
        }

        return [
            'start' => $start !== null ? sprintf('%04d-%02d', $start[0], $start[1]) : null,
            'end' => $end !== null ? sprintf('%04d-%02d', $end[0], $end[1]) : null,
            'months' => $months,
        ];
    }

    /** @return array{0:int,1:int}|null [year, month] */
    private function toYearMonth(string $token): ?array
    {
        $token = mb_strtolower(trim($token));
        if (! preg_match('/(\d{4})/', $token, $ym)) {
            return null;
        }
        $year = (int) $ym[1];
        if ($year < 1950 || $year > 2100) {
            return null;
        }
        $month = 1;
        $months = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12];
        foreach ($months as $name => $n) {
            if (str_contains($token, $name)) {
                $month = $n;
                break;
            }
        }
        if (preg_match('#(\d{1,2})[\/\.]\d{4}#', $token, $mm)) {
            $month = max(1, min(12, (int) $mm[1]));
        }

        return [$year, $month];
    }

    /**
     * @param  list<array{title?: string, company?: string, start?: ?string, end?: ?string, months?: ?int}>  $experiences
     * @param  list<string>  $expLines
     * @return list<string>
     */
    private function extractTitles(array $experiences, array $expLines): array
    {
        $titles = [];
        foreach ($experiences as $e) {
            if (! empty($e['title'])) {
                $titles[trim($e['title'])] = true;
            }
        }
        // Catch standalone title lines — but NOT full "Title at Company (dates)"
        // experience lines (already captured above), so titles stay clean.
        foreach ($expLines as $line) {
            if (preg_match('/\s(?:at|@)\s|\d{4}|[\(\)–—]/u', $line)) {
                continue;
            }
            if (preg_match('/\b(engineer|developer|manager|designer|analyst|consultant|architect|specialist|lead|director|officer|administrator|scientist|accountant|marketer|recruiter)\b/i', $line)
                && mb_strlen($line) <= 48) {
                $titles[trim($line)] = true;
            }
        }

        return array_values(array_slice(array_keys($titles), 0, 12));
    }

    /**
     * @param  list<array{title?: string, company?: string, start?: ?string, end?: ?string, months?: ?int}>  $experiences
     * @return list<string>
     */
    private function extractCompanies(array $experiences): array
    {
        $companies = [];
        foreach ($experiences as $e) {
            if (! empty($e['company'])) {
                $companies[trim($e['company'])] = true;
            }
        }

        return array_values(array_slice(array_keys($companies), 0, 12));
    }

    /**
     * @param  list<string>  $skillSection
     * @return list<string>
     */
    private function extractSkills(string $text, array $skillSection): array
    {
        // Ontology recognition over the whole CV…
        $found = SkillOntology::detectSkills($text);
        // …plus any explicit comma/bullet list under a "Skills" heading.
        foreach ($skillSection as $line) {
            foreach (preg_split('/[,;•·|\/]+/', $line) ?: [] as $token) {
                $canonical = SkillOntology::canonical($token);
                if ($canonical !== '' && mb_strlen($canonical) <= 30 && ! in_array($canonical, $found, true)
                    && preg_match('/[a-z]/i', $canonical)) {
                    $found[] = $canonical;
                }
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * @param  list<string>  $certSection
     * @return list<string>
     */
    private function extractCertifications(string $text, array $certSection): array
    {
        $out = [];
        $headingWords = ['certifications', 'certificates', 'licenses', 'licences'];
        foreach ($certSection as $line) {
            $clean = trim($line);
            if ($clean !== '' && mb_strlen($clean) <= 120 && ! in_array(mb_strtolower($clean), $headingWords, true)) {
                $out[$clean] = true;
            }
        }
        $hay = mb_strtolower($text);
        foreach (SkillOntology::CERTIFICATION_CUES as $cue) {
            if (str_contains($hay, $cue)) {
                // Capture the sentence/line containing the cue.
                if (preg_match('/[^\n.]*' . preg_quote($cue, '/') . '[^\n.]*/i', $text, $m)) {
                    $line = trim($m[0]);
                    if ($line !== '' && mb_strlen($line) <= 120 && ! in_array(mb_strtolower($line), $headingWords, true)) {
                        $out[$line] = true;
                    }
                }
            }
        }

        return array_values(array_slice(array_keys($out), 0, 12));
    }

    /**
     * @param  list<string>  $langSection
     * @return list<string>
     */
    private function extractLanguages(string $text, array $langSection): array
    {
        $scope = $langSection !== [] ? implode("\n", $langSection) : $text;

        return SkillOntology::detectLanguages($scope);
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function extractList(array $lines, int $limit): array
    {
        $out = [];
        foreach ($lines as $line) {
            $clean = trim($line, " \t-•·*—–");
            if ($clean !== '' && mb_strlen($clean) <= 160) {
                $out[$clean] = true;
            }
            if (count($out) >= $limit) {
                break;
            }
        }

        return array_values(array_keys($out));
    }

    /**
     * @param  list<array{title?: string, company?: string, start?: ?string, end?: ?string, months?: ?int}>  $experiences
     */
    private function extractYears(string $text, array $experiences): ?int
    {
        // 1) An explicit "N years of experience" statement wins.
        $best = null;
        if (preg_match_all('/(\d{1,2})\s*\+?\s*(?:years?|yrs?)(?:\s+of)?(?:\s+experience)?/i', $text, $m)) {
            foreach ($m[1] as $n) {
                $best = max($best ?? 0, (int) $n);
            }
        }
        if ($best !== null && $best > 0 && $best <= 60) {
            return $best;
        }

        // 2) Otherwise sum experience entries' months (de-overlapped naïvely).
        $months = 0;
        foreach ($experiences as $e) {
            $months += (int) ($e['months'] ?? 0);
        }
        $years = intdiv($months, 12);

        return $years > 0 && $years <= 60 ? $years : null;
    }

    private function firstMatch(string $pattern, string $text): ?string
    {
        return preg_match($pattern, $text, $m) ? $m[0] : null;
    }
}
