<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;

/**
 * Candidate 360° Intelligence — assembles a hiring brief (Executive Summary,
 * Strengths, Weaknesses, Risks, Culture Fit, Leadership, Communication, Technical
 * Depth, Recommended Jobs, Recommended Salary, Probability of Success, Interview
 * Focus Areas) by REUSING data already gathered: the latest AI assessment
 * (strengths / weaknesses / red-flags / behaviour / per-skill scores / summary),
 * the normalised profile fields, human interview feedback, open jobs, and the
 * unified {@see CandidateHealthService}. It spends **no fresh AI** — it only
 * re-projects prior analysis. Fully workspace-scoped.
 */
final class CandidateIntelligenceService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly AssessmentService $assessments,
        private readonly CandidateProfileService $profiles,
        private readonly CandidateHealthService $health,
    ) {
    }

    /**
     * @return array{
     *     has_data: bool, executive_summary: string,
     *     strengths: list<string>, weaknesses: list<string>, risks: list<string>,
     *     culture_fit: string, leadership: string, communication: string,
     *     technical_depth: array{label: string, stacks: list<string>, top_skills: list<string>},
     *     recommended_jobs: list<array{job_id: string, title: string, match: int}>,
     *     recommended_salary: ?array{range: string, basis: string},
     *     probability_of_success: array{percent: int, band: string},
     *     interview_focus_areas: list<string>
     * }
     */
    public function forCandidate(string $workspaceId, string $userId): array
    {
        $assessment = $this->assessments->latestForCandidate($workspaceId, $userId) ?? [];
        $fields = $this->profiles->fields($workspaceId, $userId);
        $health = $this->health->forCandidate($workspaceId, $userId);

        $strengths = $this->stringList($assessment['strengths'] ?? []);
        $weaknesses = $this->stringList($assessment['weaknesses'] ?? []);
        $risks = $this->risks($assessment['red_flags'] ?? []);
        $skills = $this->skillList($fields, $assessment);
        $seniority = $this->scalar($fields['seniority'] ?? '') ?: $this->seniorityFromSkills($assessment);

        return [
            'has_data' => $assessment !== [] || $skills !== [] || $health['score'] > 0,
            'executive_summary' => $this->executiveSummary($assessment, $health, $seniority, $strengths),
            'strengths' => array_slice($strengths, 0, 6),
            'weaknesses' => array_slice($weaknesses, 0, 6),
            'risks' => array_slice($risks, 0, 6),
            'culture_fit' => $this->cultureFit($assessment),
            'leadership' => $this->leadership($seniority, $strengths, $weaknesses),
            'communication' => $this->communication($workspaceId, $userId, $assessment),
            'technical_depth' => $this->technicalDepth($skills, $assessment),
            'recommended_jobs' => $this->recommendedJobs($workspaceId, $skills, $userId),
            'recommended_salary' => $this->recommendedSalary($workspaceId, $fields),
            'probability_of_success' => ['percent' => (int) $health['score'], 'band' => (string) $health['band']],
            'interview_focus_areas' => $this->interviewFocus($weaknesses, $risks, $assessment),
        ];
    }

    private function executiveSummary(array $assessment, array $health, string $seniority, array $strengths): string
    {
        if (! empty($assessment['summary'])) {
            return (string) $assessment['summary'];
        }
        $bits = [];
        $bits[] = $seniority !== '' ? "{$seniority} candidate" : 'Candidate';
        $bits[] = 'with a health score of ' . (int) $health['score'] . '/100 (' . (string) $health['band'] . ')';
        if ($strengths !== []) {
            $bits[] = 'strongest in ' . implode(', ', array_slice($strengths, 0, 2));
        }

        return ucfirst(implode(' ', $bits)) . '.';
    }

    /** @return list<string> */
    private function risks(mixed $redFlags): array
    {
        $out = [];
        foreach ((array) $redFlags as $flag) {
            if (is_array($flag)) {
                $label = (string) ($flag['label'] ?? $flag['note'] ?? $flag['title'] ?? '');
                $severity = (string) ($flag['severity'] ?? '');
                if ($label !== '') {
                    $out[] = $severity !== '' ? "{$label} ({$severity})" : $label;
                }
            } elseif (is_scalar($flag) && (string) $flag !== '') {
                $out[] = (string) $flag;
            }
        }

        return $out;
    }

    private function cultureFit(array $assessment): string
    {
        $behavior = $assessment['behavior'] ?? null;
        if (is_array($behavior)) {
            if (! empty($behavior['summary'])) {
                return (string) $behavior['summary'];
            }
            $traits = [];
            foreach (['disc', 'big_five', 'bigfive'] as $k) {
                if (! empty($behavior[$k])) {
                    $traits[] = is_array($behavior[$k]) ? implode('/', array_map('strval', $behavior[$k])) : (string) $behavior[$k];
                }
            }
            if ($traits !== []) {
                return 'Behavioural profile: ' . implode(' · ', $traits) . '.';
            }
        }

        return 'No behavioural profile yet — run an AI interview to infer culture fit.';
    }

    /** @param list<string> $strengths @param list<string> $weaknesses */
    private function leadership(string $seniority, array $strengths, array $weaknesses): string
    {
        $lead = static fn (array $xs): bool => array_filter($xs, static fn (string $s): bool => (bool) preg_match('/\b(lead|mentor|manage|owner|architec|direct)/i', $s)) !== [];
        if ($lead($strengths)) {
            return 'Shows leadership signals — evidence of leading, mentoring or owning outcomes.';
        }
        if (in_array($seniority, ['Lead / Principal', 'Manager', 'Director', 'Executive', 'Senior'], true)) {
            return "Seniority ({$seniority}) implies leadership scope; confirm scope of people/decision ownership.";
        }
        if ($lead($weaknesses)) {
            return 'Leadership flagged as a growth area — probe readiness for ownership.';
        }

        return 'Individual-contributor profile — limited explicit leadership signal.';
    }

    private function communication(string $workspaceId, string $userId, array $assessment): string
    {
        $row = $this->connection->selectOne(
            'SELECT AVG(f.rating) AS r, COUNT(*) AS c FROM interview_feedback f
               JOIN interviews i ON i.id = f.interview_id
              WHERE f.workspace_id = ? AND i.candidate_user_id = ?',
            [$workspaceId, $userId],
        );
        if ($row !== null && ($row['c'] ?? 0) > 0 && $row['r'] !== null) {
            $avg = round((float) $row['r'], 1);

            return "Interviewers rated communication {$avg}/5 across " . (int) $row['c'] . ' evaluation(s).';
        }
        if (! empty($assessment['summary'])) {
            return 'Inferred from CV/interview analysis — see the AI summary and interview transcripts.';
        }

        return 'No interviewer feedback yet — assess communication in the next interview.';
    }

    /**
     * @param  list<string>  $skills
     * @return array{label: string, stacks: list<string>, top_skills: list<string>}
     */
    private function technicalDepth(array $skills, array $assessment): array
    {
        $scored = [];
        $skillScores = $assessment['skills'] ?? null;
        if (is_array($skillScores)) {
            foreach ($skillScores as $name => $entry) {
                $score = is_array($entry) ? (int) ($entry['score'] ?? 0) : (is_numeric($entry) ? (int) $entry : 0);
                if ($score > 0) {
                    $scored[(string) $name] = $score;
                }
            }
            arsort($scored);
        }
        $top = $scored !== [] ? array_slice(array_keys($scored), 0, 5) : array_slice($skills, 0, 5);
        $avg = $scored !== [] ? array_sum($scored) / count($scored) : 0;
        $label = match (true) {
            $avg >= 80 => 'Deep technical expertise',
            $avg >= 60 => 'Solid technical foundation',
            $avg > 0 => 'Developing technical depth',
            $skills !== [] => 'Skills listed; depth not yet assessed',
            default => 'No technical signal yet',
        };

        return ['label' => $label, 'stacks' => array_slice($skills, 0, 8), 'top_skills' => $top];
    }

    /**
     * @param  list<string>  $skills
     * @return list<array{job_id: string, title: string, match: int}>
     */
    private function recommendedJobs(string $workspaceId, array $skills, string $userId): array
    {
        $jobs = $this->connection->select(
            "SELECT id, title, required_skills, seniority FROM jobs
              WHERE workspace_id = ? AND status = 'published' AND deleted_at IS NULL
              ORDER BY created_at DESC LIMIT 40",
            [$workspaceId],
        );
        if ($jobs === [] || $skills === []) {
            return [];
        }
        $applied = $this->appliedJobIds($workspaceId, $userId);
        $candidateSkills = array_map(static fn (string $s): string => strtolower(trim($s)), $skills);

        $ranked = [];
        foreach ($jobs as $job) {
            if (in_array((string) $job['id'], $applied, true)) {
                continue; // already applied — recommend new roles
            }
            $req = strtolower((string) ($job['required_skills'] ?? '') . ' ' . (string) $job['title']);
            $hits = 0;
            foreach ($candidateSkills as $sk) {
                if ($sk !== '' && str_contains($req, $sk)) {
                    $hits++;
                }
            }
            if ($hits > 0) {
                $ranked[] = [
                    'job_id' => (string) $job['id'],
                    'title' => (string) $job['title'],
                    'match' => (int) min(100, round($hits / max(1, count($candidateSkills)) * 100)),
                ];
            }
        }
        usort($ranked, static fn (array $a, array $b): int => $b['match'] <=> $a['match']);

        return array_slice($ranked, 0, 4);
    }

    /**
     * @param  array<string, string|list<string>>  $fields
     * @return ?array{range: string, basis: string}
     */
    private function recommendedSalary(string $workspaceId, array $fields): ?array
    {
        $expected = (int) $this->scalar($fields['expected_salary'] ?? '');
        $current = (int) $this->scalar($fields['current_salary'] ?? '');

        if ($expected > 0) {
            $low = (int) round($expected * 0.95);
            $high = (int) round($expected * 1.15);

            return ['range' => '$' . number_format($low) . ' – $' . number_format($high), 'basis' => 'candidate expectation'];
        }

        // Fall back to the workspace's own published salary bands.
        $band = $this->connection->selectOne(
            "SELECT AVG(salary_min) AS lo, AVG(salary_max) AS hi FROM jobs
              WHERE workspace_id = ? AND status = 'published' AND salary_min IS NOT NULL AND salary_max IS NOT NULL",
            [$workspaceId],
        );
        if ($band !== null && $band['lo'] !== null && $band['hi'] !== null) {
            return [
                'range' => '$' . number_format((int) $band['lo']) . ' – $' . number_format((int) $band['hi']),
                'basis' => 'workspace salary bands',
            ];
        }
        if ($current > 0) {
            return ['range' => 'from $' . number_format((int) round($current * 1.05)), 'basis' => 'current salary + typical uplift'];
        }

        return null;
    }

    /**
     * @param  list<string>  $weaknesses
     * @param  list<string>  $risks
     * @return list<string>
     */
    private function interviewFocus(array $weaknesses, array $risks, array $assessment): array
    {
        $focus = [];
        foreach (array_slice($weaknesses, 0, 3) as $w) {
            $focus[] = 'Probe: ' . $w;
        }
        foreach (array_slice($risks, 0, 2) as $r) {
            $focus[] = 'Clarify risk: ' . $r;
        }
        // Lowest-scoring assessed skills are worth verifying live.
        $skillScores = $assessment['skills'] ?? null;
        if (is_array($skillScores)) {
            $low = [];
            foreach ($skillScores as $name => $entry) {
                $score = is_array($entry) ? (int) ($entry['score'] ?? 0) : (is_numeric($entry) ? (int) $entry : 0);
                if ($score > 0 && $score < 60) {
                    $low[(string) $name] = $score;
                }
            }
            asort($low);
            foreach (array_slice(array_keys($low), 0, 2) as $skill) {
                $focus[] = 'Verify skill depth: ' . $skill;
            }
        }
        if ($focus === []) {
            $focus[] = 'No specific gaps flagged — run a structured interview across the role rubric.';
        }

        return array_slice($focus, 0, 6);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** @return list<string> */
    private function appliedJobIds(string $workspaceId, string $userId): array
    {
        $rows = $this->connection->select(
            'SELECT job_id FROM applications WHERE workspace_id = ? AND user_id = ? AND deleted_at IS NULL',
            [$workspaceId, $userId],
        );

        return array_map(static fn (array $r): string => (string) $r['job_id'], $rows);
    }

    /**
     * @param  array<string, string|list<string>>  $fields
     * @return list<string>
     */
    private function skillList(array $fields, array $assessment): array
    {
        $skills = [];
        $fromFields = $fields['skills'] ?? [];
        foreach ((array) $fromFields as $s) {
            if (is_scalar($s) && trim((string) $s) !== '') {
                $skills[] = trim((string) $s);
            }
        }
        $skillScores = $assessment['skills'] ?? null;
        if (is_array($skillScores)) {
            foreach (array_keys($skillScores) as $name) {
                $skills[] = (string) $name;
            }
        }

        return array_values(array_unique($skills));
    }

    private function seniorityFromSkills(array $assessment): string
    {
        return (string) ($assessment['recommendation'] ?? '') === 'strong' ? 'Senior' : '';
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        $out = [];
        foreach ((array) $value as $v) {
            if (is_scalar($v) && trim((string) $v) !== '') {
                $out[] = trim((string) $v);
            } elseif (is_array($v) && isset($v['label'])) {
                $out[] = (string) $v['label'];
            }
        }

        return array_values(array_unique($out));
    }

    private function scalar(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
