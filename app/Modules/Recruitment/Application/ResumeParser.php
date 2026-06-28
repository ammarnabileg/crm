<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

/**
 * A deterministic, dependency-free résumé/CV text parser. It extracts structured
 * fields (contact, links, experience, skills) from plain CV text so a recruiter
 * can pre-fill a candidate's structured profile instead of retyping it. The AI
 * still does the deep assessment; this is mechanical extraction, fully testable.
 *
 * (PDF/DOCX byte-extraction needs external tooling that isn't guaranteed in every
 * environment, so the input here is text — from a .txt upload or a paste.)
 */
final class ResumeParser
{
    /** Common skill/tech keywords to detect (word-boundary, case-insensitive). */
    private const SKILL_KEYWORDS = [
        'PHP', 'Python', 'JavaScript', 'TypeScript', 'Java', 'C#', 'C++', 'Go', 'Rust', 'Ruby', 'Kotlin', 'Swift',
        'React', 'Vue', 'Angular', 'Svelte', 'Node.js', 'Laravel', 'Symfony', 'Django', 'Flask', 'Spring', 'Rails', '.NET',
        'MySQL', 'PostgreSQL', 'MongoDB', 'Redis', 'SQLite', 'Elasticsearch',
        'AWS', 'Azure', 'GCP', 'Docker', 'Kubernetes', 'Terraform', 'Linux', 'Git', 'CI/CD',
        'HTML', 'CSS', 'Tailwind', 'SASS', 'GraphQL', 'REST', 'gRPC',
        'Machine Learning', 'Deep Learning', 'TensorFlow', 'PyTorch', 'NLP', 'Pandas', 'NumPy',
        'Agile', 'Scrum', 'Kanban', 'Jira', 'Figma', 'Photoshop', 'SEO',
        'Leadership', 'Communication', 'Project Management', 'Recruiting', 'Sales', 'Marketing', 'Accounting',
    ];

    /**
     * @return array{
     *   email?: string, phone?: string, years_experience?: int,
     *   skills?: list<string>, linkedin?: string, github?: string, links?: list<string>
     * }
     */
    public function parse(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $out = [];

        if (preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $text, $m)) {
            $out['email'] = strtolower($m[0]);
        }

        $phone = $this->extractPhone($text);
        if ($phone !== null) {
            $out['phone'] = $phone;
        }

        $years = $this->extractYears($text);
        if ($years !== null) {
            $out['years_experience'] = $years;
        }

        $skills = $this->extractSkills($text);
        if ($skills !== []) {
            $out['skills'] = $skills;
        }

        $links = [];
        if (preg_match_all('#https?://[^\s)\]<>"]+#i', $text, $mm)) {
            foreach ($mm[0] as $url) {
                $clean = rtrim($url, '.,);');
                $host = strtolower((string) parse_url($clean, PHP_URL_HOST));
                if (str_contains($host, 'linkedin.') && ! isset($out['linkedin'])) {
                    $out['linkedin'] = $clean;
                } elseif (str_contains($host, 'github.') && ! isset($out['github'])) {
                    $out['github'] = $clean;
                } else {
                    $links[$clean] = true;
                }
            }
        }
        if ($links !== []) {
            $out['links'] = array_values(array_slice(array_keys($links), 0, 8));
        }

        return $out;
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

    private function extractYears(string $text): ?int
    {
        $best = null;
        if (preg_match_all('/(\d{1,2})\s*\+?\s*(?:years?|yrs?)(?:\s+of)?(?:\s+experience)?/i', $text, $m)) {
            foreach ($m[1] as $n) {
                $best = max($best ?? 0, (int) $n);
            }
        }

        return ($best !== null && $best > 0 && $best <= 60) ? $best : null;
    }

    /** @return list<string> */
    private function extractSkills(string $text): array
    {
        $found = [];
        foreach (self::SKILL_KEYWORDS as $skill) {
            $pattern = '/(?<![A-Za-z0-9])' . preg_quote($skill, '/') . '(?![A-Za-z0-9])/i';
            if (preg_match($pattern, $text)) {
                $found[$skill] = true;
            }
        }

        return array_keys($found);
    }
}
