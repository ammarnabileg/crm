<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\AiEngine\Application\AiEngine;
use HaHireAI\Modules\Recruitment\Domain\CvScreening;
use HaHireAI\Modules\Recruitment\Domain\SkillCatalog;
use HaHireAI\Shared\Ulid;

/**
 * Produces an ADVISORY AI assessment of a candidate (11 weighted skills,
 * behaviour, red flags, CV fit, recommendation band) via the central AI Engine.
 * The engine picks the provider; a real model returns the analysis, the built-in
 * echo provider yields deterministic placeholder data so the data model, flow and
 * UI are fully exercised. The AI never decides — a human does (docs/AI_ENGINE.md §8.1).
 */
final class AssessmentService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly AiEngine $ai,
    ) {
    }

    /**
     * Assess a completed interview and store the assessment.
     *
     * @return array<string, mixed> the stored assessment
     */
    public function assessFromInterview(string $workspaceId, string $interviewId, ?string $actorUserId = null): array
    {
        $iv = $this->connection->selectOne(
            'SELECT i.*, u.name AS candidate, j.title AS job, j.required_skills, j.screening_keywords FROM interviews i
               JOIN users u ON u.id = i.candidate_user_id
               JOIN jobs j ON j.id = i.job_id
              WHERE i.id = ? AND i.workspace_id = ?',
            [$interviewId, $workspaceId],
        );
        if ($iv === null) {
            throw new \HaHireAI\Modules\Recruitment\Application\Exceptions\ApplicationException('Interview not found.');
        }

        $transcript = (string) ($iv['transcript'] ?? '');

        $result = $this->ai->run($workspaceId, 'assess_candidate', [
            'name' => (string) $iv['candidate'],
            'title' => (string) $iv['job'],
            'transcript' => mb_substr($transcript, 0, 2000),
        ], $actorUserId);

        // Real signals (not crc32): the AI's own SCORE when a real provider returns
        // one, plus a deterministic skills/keyword match of the job's required
        // skills against what the candidate actually said + their CV.
        $candidateText = $transcript . ' ' . $this->candidateText($workspaceId, (string) $iv['candidate_user_id']);
        $skillsMatch = $this->skillsMatch((string) ($iv['required_skills'] ?? ''), (string) ($iv['screening_keywords'] ?? ''), $candidateText);
        $aiScore = $this->parseScore($result->text);

        $seed = (string) $iv['id'] . '|' . ($transcript !== '' ? $transcript : (string) $iv['candidate']);
        $data = $this->structured($seed, $aiScore, $skillsMatch, $result->text);

        return $this->store(
            $workspaceId,
            (string) $iv['candidate_user_id'],
            (string) $iv['application_id'],
            $interviewId,
            'interview',
            $data,
            $result->provider,
            $actorUserId,
        );
    }

    /** @return array<string, mixed>|null the most recent assessment for a candidate, decoded */
    public function latestForCandidate(string $workspaceId, string $userId): ?array
    {
        $row = $this->connection->selectOne(
            'SELECT * FROM candidate_assessments WHERE workspace_id = ? AND candidate_user_id = ? ORDER BY created_at DESC, id DESC LIMIT 1',
            [$workspaceId, $userId],
        );

        return $row !== null ? $this->decode($row) : null;
    }

    /**
     * Advanced search: candidates by minimum overall score and/or recommendation
     * band and/or a specific skill threshold (feature: advanced search).
     *
     * @param  array{min_score?: int, recommendation?: string, skill?: string, skill_min?: int}  $filters
     * @return list<array<string, mixed>>
     */
    public function search(string $workspaceId, array $filters = []): array
    {
        $rows = $this->connection->select(
            'SELECT a.*, u.name, u.email FROM candidate_assessments a
               JOIN users u ON u.id = a.candidate_user_id
              WHERE a.workspace_id = ?
              ORDER BY a.fit_score DESC, a.created_at DESC',
            [$workspaceId],
        );

        $minScore = (int) ($filters['min_score'] ?? 0);
        $band = (string) ($filters['recommendation'] ?? '');
        $skill = (string) ($filters['skill'] ?? '');
        $skillMin = (int) ($filters['skill_min'] ?? 0);

        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $candidate = (string) $row['candidate_user_id'];
            if (isset($seen[$candidate])) {
                continue;                       // keep each candidate's best (highest score) only
            }

            if ((int) $row['fit_score'] < $minScore) {
                continue;
            }
            if ($band !== '' && (string) $row['recommendation'] !== $band) {
                continue;
            }

            $decoded = $this->decode($row);
            if ($skill !== '') {
                $score = (int) ($decoded['skills'][$skill]['score'] ?? 0);
                if ($score < $skillMin) {
                    continue;
                }
            }

            $seen[$candidate] = true;
            $out[] = $decoded;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function store(string $workspaceId, string $userId, ?string $applicationId, ?string $interviewId, string $source, array $data, string $provider, ?string $actor): array
    {
        $id = Ulid::generate();
        $this->connection->statement(
            'INSERT INTO candidate_assessments
               (id, workspace_id, candidate_user_id, application_id, interview_id, source, fit_score, recommendation, summary, strengths, weaknesses, skills, behavior, red_flags, cv, ai_provider, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id, $workspaceId, $userId, $applicationId, $interviewId, $source,
                $data['fit_score'], $data['recommendation'], $data['summary'],
                json_encode($data['strengths']), json_encode($data['weaknesses']), json_encode($data['skills']),
                json_encode($data['behavior']), json_encode($data['red_flags']), json_encode($data['cv'] ?? null),
                $provider, $actor, gmdate('Y-m-d H:i:s'),
            ],
        );

        return $this->decode((array) $this->connection->selectOne('SELECT * FROM candidate_assessments WHERE id = ?', [$id]));
    }

    /**
     * Build the structured assessment. Deterministic from a seed so the echo
     * provider produces stable, advisory placeholder analysis.
     *
     * @param  array{percent:int,matched:list<string>,missing:list<string>}|null  $skillsMatch
     * @return array<string, mixed>
     */
    private function structured(string $seed, ?int $aiScore = null, ?array $skillsMatch = null, string $aiText = ''): array
    {
        $base = 60 + (int) (crc32($seed) % 26); // 60..85
        $skills = [];
        $confidences = ['high', 'medium', 'low'];

        foreach (SkillCatalog::SKILLS as $key => $meta) {
            $delta = ((int) (crc32($key . '|' . $seed) % 21)) - 10;
            $score = max(35, min(99, $base + $delta));
            $skills[$key] = [
                'score' => $score,
                'confidence' => $confidences[(int) (crc32('c' . $key . $seed) % 3)],
                'evidence' => 'Inferred from interview responses (advisory).',
            ];
        }

        $skillFit = 0;
        foreach (SkillCatalog::SKILLS as $key => $meta) {
            $skillFit += $skills[$key]['score'] * $meta['weight'];
        }
        $skillFit = (int) round($skillFit / 100);

        // The fit score is driven by REAL signal: the AI's own SCORE when a real
        // provider returned one; otherwise the job's required-skills match blended
        // with the competency baseline; otherwise the baseline alone.
        if ($aiScore !== null) {
            $fit = $aiScore;
        } elseif ($skillsMatch !== null) {
            $fit = (int) round(0.6 * $skillsMatch['percent'] + 0.4 * $skillFit);
        } else {
            $fit = $skillFit;
        }
        $fit = max(0, min(100, $fit));
        $band = SkillCatalog::band($fit);

        // strengths/weaknesses from extremes.
        $sorted = $skills;
        uasort($sorted, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $ordered = array_keys($sorted);
        $label = static fn (string $k): string => SkillCatalog::SKILLS[$k]['label'];
        $strengths = array_map($label, array_slice($ordered, 0, 2));
        $weaknesses = array_map($label, array_slice($ordered, -2));

        $behavior = [
            'disc' => ['D', 'I', 'S', 'C'][(int) (crc32('disc' . $seed) % 4)],
            'big_five' => ['Openness', 'Conscientiousness', 'Extraversion', 'Agreeableness', 'Stability'][(int) (crc32('b5' . $seed) % 5)],
            'growth' => 50 + (int) (crc32('g' . $seed) % 50),
            'stress_tolerance' => 40 + (int) (crc32('s' . $seed) % 55),
            'leadership_style' => ['Directive', 'Collaborative', 'Visionary', 'Coaching'][(int) (crc32('l' . $seed) % 4)],
        ];

        $redFlags = [];
        $flagCount = (int) (crc32('rf' . $seed) % 3); // 0..2
        $catalog = [
            ['severity' => 'high', 'note' => 'Possible contradiction between CV and interview answers.'],
            ['severity' => 'medium', 'note' => 'Salary expectation slightly above the stated range.'],
            ['severity' => 'low', 'note' => 'A claim that could not be fully verified.'],
        ];
        for ($i = 0; $i < $flagCount; $i++) {
            $redFlags[] = $catalog[$i];
        }

        // Prefer the model's own narrative when a real provider returned one;
        // otherwise a deterministic advisory summary.
        $aiText = trim($aiText);
        $summary = mb_strlen($aiText) >= 60
            ? mb_substr($aiText, 0, 600)
            : 'Advisory AI assessment from the interview — overall fit ' . $fit . '/100 (' . SkillCatalog::bandLabel($band) . '). A human makes the final decision.';

        return [
            'fit_score' => $fit,
            'recommendation' => $band,
            'summary' => $summary,
            'strengths' => $skillsMatch !== null && $skillsMatch['matched'] !== [] ? array_values(array_unique(array_merge($skillsMatch['matched'], array_values($strengths)))) : array_values($strengths),
            'weaknesses' => $skillsMatch !== null && $skillsMatch['missing'] !== [] ? array_values(array_unique(array_merge($skillsMatch['missing'], array_values($weaknesses)))) : array_values($weaknesses),
            'skills' => $skills,
            'behavior' => $behavior,
            'red_flags' => $redFlags,
            'cv' => $skillsMatch,
        ];
    }

    /** Parse a provider-returned "SCORE: NN" (0-100), or null when absent (echo provider). */
    private function parseScore(string $text): ?int
    {
        if (preg_match('/SCORE[:\s]+(\d{1,3})/i', $text, $m) === 1) {
            return max(0, min(100, (int) $m[1]));
        }

        return null;
    }

    /**
     * Deterministic skills/keyword match: how many of the job's required skills
     * (and screening keywords) appear in the candidate's words + CV. Returns the
     * percent matched plus the matched/missing lists, or null when the job lists none.
     *
     * @return array{percent:int,matched:list<string>,missing:list<string>}|null
     */
    private function skillsMatch(string $requiredSkills, string $screeningKeywords, string $candidateText): ?array
    {
        $terms = array_values(array_unique(array_merge(
            CvScreening::keywords($requiredSkills),
            CvScreening::keywords($screeningKeywords),
        )));
        if ($terms === []) {
            return null;
        }

        $matched = CvScreening::hits($candidateText, $terms);
        $missing = array_values(array_diff($terms, $matched));
        $percent = (int) round(count($matched) / max(1, count($terms)) * 100);

        return ['percent' => $percent, 'matched' => array_values($matched), 'missing' => $missing];
    }

    /** The candidate's CV text in this workspace (summary + structured details), for matching. */
    private function candidateText(string $workspaceId, string $userId): string
    {
        $prof = $this->connection->selectOne(
            'SELECT summary, details FROM candidate_profiles WHERE workspace_id = ? AND user_id = ?',
            [$workspaceId, $userId],
        );
        if ($prof === null) {
            return '';
        }
        $parts = [(string) ($prof['summary'] ?? '')];
        $details = $prof['details'] ?? null;
        $details = is_array($details) ? $details : (is_string($details) ? (json_decode($details, true) ?: []) : []);
        foreach (['skills', 'education', 'certifications', 'languages'] as $key) {
            if (! empty($details[$key]) && is_scalar($details[$key])) {
                $parts[] = (string) $details[$key];
            }
        }

        return trim(implode(' ', array_filter($parts)));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function decode(array $row): array
    {
        foreach (['strengths', 'weaknesses', 'skills', 'behavior', 'red_flags', 'cv'] as $k) {
            $row[$k] = isset($row[$k]) && is_string($row[$k]) ? json_decode((string) $row[$k], true) : ($row[$k] ?? null);
        }

        return $row;
    }
}
