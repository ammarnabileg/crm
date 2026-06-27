<?php

declare(strict_types=1);

namespace App\Services\Cv;

use App\Services\AI\AiGateway;
use App\Services\AI\AiPrompt;

/**
 * Turns raw CV text into structured candidate data — the "read the CV well" engine.
 *
 * The primary path uses the workspace's OWN AI provider (the existing AiGateway /
 * per-tenant keys) for high-quality extraction: name, contact, headline, summary,
 * years of experience, skills, work history and education as strict JSON. When no
 * AI key is configured (or the call fails / there is no network), it falls back to a
 * dependency-free heuristic pass so a profile is still populated — never a hard fail.
 *
 * The gateway is injectable so the whole thing is tested offline with a fake provider.
 */
final class CvParser
{
    private AiGateway $gateway;

    public function __construct(?AiGateway $gateway = null)
    {
        $this->gateway = $gateway ?? AiGateway::make();
    }

    /**
     * @return array{
     *   name:?string, email:?string, phone:?string, headline:?string, summary:?string,
     *   current_title:?string, city:?string, total_experience_years:?float,
     *   skills:string[], experiences:array<int,array{title:?string,company:?string,start:?string,end:?string}>,
     *   educations:array<int,array{degree:?string,institution:?string,field:?string}>,
     *   source:string
     * }
     */
    /**
     * @param array<string,mixed> $aiContext Extra context merged into the gateway call
     *        (e.g. explicit `candidates` for routing/tests). Normally empty — the
     *        workspace's configured providers are resolved automatically.
     */
    public function parse(string $text, ?int $workspaceId = null, array $aiContext = []): array
    {
        $text = trim($text);
        if ($text === '') {
            return $this->empty('none');
        }

        $aiData = $this->viaAi($text, $workspaceId, $aiContext);
        if ($aiData !== null) {
            return $aiData;
        }

        return $this->viaHeuristics($text);
    }

    /**
     * @param array<string,mixed> $aiContext
     * @return array<string,mixed>|null Null when the AI path is unavailable/failed.
     */
    private function viaAi(string $text, ?int $workspaceId, array $aiContext = []): ?array
    {
        $prompt = new AiPrompt(
            [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => "CV / résumé text:\n\n" . $text],
            ],
            'chat',
            ['temperature' => 0.0, 'max_tokens' => 1800],
        );

        try {
            $result = $this->gateway->complete($prompt, array_merge([
                'workspace_id' => $workspaceId,
                'capability'   => 'cv_parse',
            ], $aiContext));
        } catch (\Throwable) {
            return null;
        }

        if (! $result->ok || trim($result->text) === '') {
            return null;
        }

        $json = $this->decodeJson($result->text);
        if ($json === null) {
            return null;
        }

        return $this->normalise($json, 'ai');
    }

    private function systemPrompt(): string
    {
        return 'You are an expert recruitment CV parser. Read the candidate CV and return ONLY a single '
            . 'minified JSON object (no prose, no code fence) with exactly these keys: '
            . '"name" (string), "email" (string), "phone" (string), "headline" (a short professional '
            . 'title, string), "summary" (2-3 sentence professional summary, string), '
            . '"current_title" (string), "city" (string), "total_experience_years" (number), '
            . '"skills" (array of short strings), "experiences" (array of objects with '
            . '"title","company","start","end"), "educations" (array of objects with '
            . '"degree","institution","field"). Use empty string/array/0 when unknown. '
            . 'Do not invent facts that are not in the CV.';
    }

    /**
     * Find and decode the JSON object in the model's reply (tolerates a stray code
     * fence or leading prose).
     *
     * @return array<string,mixed>|null
     */
    private function decodeJson(string $reply): ?array
    {
        $reply = trim($reply);
        $reply = (string) preg_replace('/^```(?:json)?|```$/m', '', $reply);

        $start = strpos($reply, '{');
        $end = strrpos($reply, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($reply, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function normalise(array $data, string $source): array
    {
        $str = static fn (string $k): ?string => isset($data[$k]) && is_scalar($data[$k]) && trim((string) $data[$k]) !== ''
            ? trim((string) $data[$k]) : null;

        $skills = [];
        foreach ((array) ($data['skills'] ?? []) as $s) {
            if (is_scalar($s) && trim((string) $s) !== '') {
                $skills[] = trim((string) $s);
            }
        }

        $experiences = [];
        foreach ((array) ($data['experiences'] ?? []) as $e) {
            if (! is_array($e)) {
                continue;
            }
            $experiences[] = [
                'title'   => $this->pick($e, 'title'),
                'company' => $this->pick($e, 'company'),
                'start'   => $this->pick($e, 'start'),
                'end'     => $this->pick($e, 'end'),
            ];
        }

        $educations = [];
        foreach ((array) ($data['educations'] ?? []) as $ed) {
            if (! is_array($ed)) {
                continue;
            }
            $educations[] = [
                'degree'      => $this->pick($ed, 'degree'),
                'institution' => $this->pick($ed, 'institution'),
                'field'       => $this->pick($ed, 'field'),
            ];
        }

        return [
            'name'                   => $str('name'),
            'email'                  => $str('email'),
            'phone'                  => $str('phone'),
            'headline'               => $str('headline'),
            'summary'                => $str('summary'),
            'current_title'          => $str('current_title'),
            'city'                   => $str('city'),
            'total_experience_years' => isset($data['total_experience_years']) && is_numeric($data['total_experience_years'])
                ? round((float) $data['total_experience_years'], 1) : null,
            'skills'                 => array_values(array_unique(array_slice($skills, 0, 40))),
            'experiences'            => array_slice($experiences, 0, 20),
            'educations'             => array_slice($educations, 0, 20),
            'source'                 => $source,
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function pick(array $row, string $key): ?string
    {
        return isset($row[$key]) && is_scalar($row[$key]) && trim((string) $row[$key]) !== ''
            ? trim((string) $row[$key]) : null;
    }

    /**
     * Dependency-free fallback: recover the obvious fields with patterns + a common
     * skills lexicon. Lower quality than the AI path, but never nothing.
     *
     * @return array<string,mixed>
     */
    private function viaHeuristics(string $text): array
    {
        $out = $this->empty('heuristic');

        if (preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $text, $m)) {
            $out['email'] = $m[0];
        }
        if (preg_match('/(\+?\d[\d\s().\-]{7,}\d)/', $text, $m)) {
            $out['phone'] = trim($m[1]);
        }

        // Name: the first short, mostly-alphabetic line near the top.
        foreach (preg_split('/\n/', $text) ?: [] as $line) {
            $line = trim($line);
            $words = preg_split('/\s+/', $line) ?: [];
            if ($line !== '' && count($words) >= 2 && count($words) <= 4
                && preg_match('/^[\p{L}\p{M}.\'\- ]+$/u', $line) && mb_strlen($line) <= 60) {
                $out['name'] = $line;
                break;
            }
        }

        // Years of experience: "12 years" / "12+ years of experience".
        if (preg_match('/(\d{1,2})\s*\+?\s*years?\b/i', $text, $m)) {
            $out['total_experience_years'] = (float) $m[1];
        }

        $out['skills'] = $this->matchSkills($text);
        $out['summary'] = mb_substr(trim((string) preg_replace('/\s+/', ' ', $text)), 0, 300) ?: null;

        return $out;
    }

    /**
     * @return string[]
     */
    private function matchSkills(string $text): array
    {
        $lexicon = [
            'PHP', 'Laravel', 'Symfony', 'JavaScript', 'TypeScript', 'Vue', 'React', 'Angular', 'Node.js',
            'Python', 'Django', 'Flask', 'Java', 'Spring', 'C#', '.NET', 'Go', 'Rust', 'Ruby', 'Rails',
            'MySQL', 'PostgreSQL', 'MariaDB', 'MongoDB', 'Redis', 'SQL', 'Docker', 'Kubernetes', 'AWS',
            'Azure', 'GCP', 'Linux', 'Git', 'CI/CD', 'REST', 'GraphQL', 'HTML', 'CSS', 'Tailwind',
            'Agile', 'Scrum', 'Project Management', 'Leadership', 'Communication', 'Arabic', 'English',
        ];
        $found = [];
        foreach ($lexicon as $skill) {
            if (preg_match('/(?<![\p{L}])' . preg_quote($skill, '/') . '(?![\p{L}])/iu', $text)) {
                $found[] = $skill;
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * @return array<string,mixed>
     */
    private function empty(string $source): array
    {
        return [
            'name' => null, 'email' => null, 'phone' => null, 'headline' => null, 'summary' => null,
            'current_title' => null, 'city' => null, 'total_experience_years' => null,
            'skills' => [], 'experiences' => [], 'educations' => [], 'source' => $source,
        ];
    }
}
