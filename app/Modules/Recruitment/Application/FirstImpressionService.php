<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Core\Contracts\SocialProfileProbe;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Recruitment\Domain\FirstImpression\CandidateInsights;
use HaHireAI\Modules\Recruitment\Domain\FirstImpression\FirstImpressionScore;
use HaHireAI\Modules\Recruitment\Domain\FirstImpression\JobProfile;
use HaHireAI\Modules\Recruitment\Domain\FirstImpression\ResumeAnalysisEngine;
use HaHireAI\Modules\Recruitment\Domain\FirstImpression\ResumeAnalysisResult;
use HaHireAI\Modules\Recruitment\Domain\FirstImpression\SocialRelevanceScorer;
use HaHireAI\Modules\Recruitment\Domain\FirstImpression\SocialScoring;
use HaHireAI\Modules\Recruitment\Domain\Resume\ExtractedText;
use HaHireAI\Modules\Recruitment\Domain\Resume\ParsedResume;
use HaHireAI\Modules\Recruitment\Domain\Resume\ResumeStructurer;
use HaHireAI\Shared\Ulid;
use Throwable;

/**
 * The First Impression Engine orchestrator — the ZERO-AI gate that runs between
 * "Apply" and the (paid) AI interview. It:
 *   1. parses the chosen CV (cached text) into structured data,
 *   2. runs the Resume Analysis Engine against the job (the basis: closeness to
 *      the job),
 *   3. asks the Integration Platform's SocialProfileProbe for public social data
 *      and scores its RELEVANCE (optional ±30 boost; absent/neutral = no effect),
 *   4. combines them into the First Impression Credibility Score + decision,
 *   5. persists everything into the NORMALISED tables (never a JSON blob), and
 *   6. publishes events so the Workflow Engine / Notifications can react.
 *
 * It spends ZERO AI credits — no provider, no LLM — by construction.
 */
final class FirstImpressionService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ResumeStructurer $structurer,
        private readonly ResumeAnalysisEngine $analysis,
        private readonly UserResumeService $resumes,
        private readonly EventDispatcher $events,
        private readonly ?SocialProfileProbe $social = null,
    ) {
    }

    /**
     * Run the gate for one application and persist the report.
     *
     * @param  array<string, mixed>  $job   the jobs row (with the per-job config)
     * @param  list<string>  $links         the candidate's social URLs
     * @return array{report_id: string, overall: int, passed: bool, decision: string, threshold: int, resume_score: int, job_match_score: int, social_score: ?int, social_boost: int}
     */
    public function run(string $workspaceId, array $job, string $applicationId, string $candidateUserId, ?string $resumeId, array $links, string $coverNote = ''): array
    {
        // 1) Résumé text → structured résumé.
        $parsed = $this->resumeText($candidateUserId, $resumeId, $coverNote);
        $resume = $this->structurer->structure($parsed);

        // 2) Engine 1 — résumé/job relevance (the basis).
        $jobProfile = JobProfile::fromJobRow($job);
        $resumeResult = $this->analysis->analyze($resume, $jobProfile);

        // 2b) Engine 1b — Candidate Intelligence (advisory, never changes the score).
        $insights = CandidateInsights::derive($resume, $jobProfile);

        // 3) Engine 2 — social credibility (optional boost; neutral when absent).
        [$snapshots, $scored] = $this->probeSocial($links, $jobProfile);
        $roll = SocialScoring::roll($scored);
        $socialConfidence = SocialScoring::confidence($scored);

        // 4) Combine + decide.
        $threshold = (int) ($job['min_first_impression_score'] ?? 65);
        $fi = FirstImpressionScore::decide($resumeResult, $roll, $threshold, $socialConfidence);

        // 5) Persist (normalised, transactional).
        $reportId = $this->persist($workspaceId, $applicationId, $candidateUserId, (string) ($job['id'] ?? ''), $resumeId, $parsed, $resume, $resumeResult, $fi, $snapshots, $scored, $roll, $socialConfidence, $insights);

        // 6) React-ready events on the bus.
        $this->emit($workspaceId, $candidateUserId, $applicationId, (string) ($job['id'] ?? ''), $reportId, $fi);

        return [
            'report_id' => $reportId,
            'overall' => $fi->overall,
            'passed' => $fi->passed,
            'decision' => $fi->decision(),
            'threshold' => $fi->threshold,
            'resume_score' => $fi->resumeScore,
            'job_match_score' => $fi->jobMatchScore,
            'social_score' => $fi->socialScore,
            'social_boost' => $fi->socialBoost,
        ];
    }

    /**
     * HR manually overrides a filtered report to allow the AI interview. The
     * report is preserved (audit), flagged overridden, and marked passed.
     */
    public function override(string $workspaceId, string $reportId, string $byUserId): ?array
    {
        $report = $this->connection->selectOne(
            'SELECT * FROM first_impression_reports WHERE id = ? AND workspace_id = ?',
            [$reportId, $workspaceId],
        );
        if ($report === null) {
            return null;
        }
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'UPDATE first_impression_reports SET overridden = 1, overridden_by = ?, overridden_at = ?, passed = 1, decision = ?, updated_at = ?
              WHERE id = ? AND workspace_id = ?',
            [$byUserId, $now, 'passed', $now, $reportId, $workspaceId],
        );
        // Past-tense event name per Constitution §7; carries the report headline
        // for parity with completed/passed/failed so Workflow nodes see the score.
        $this->events->dispatch('first_impression.overridden', [
            'workspace_id' => $workspaceId,
            'report_id' => $reportId,
            'application_id' => $report['application_id'] ?? null,
            'job_id' => $report['job_id'] ?? null,
            'user_id' => $byUserId,
            'candidate_user_id' => $report['candidate_user_id'] ?? null,
            'overall_score' => (int) ($report['overall_score'] ?? 0),
            'threshold' => (int) ($report['threshold'] ?? 0),
            'passed' => true,
            'decision' => 'passed',
        ]);

        return $report;
    }

    private function resumeText(string $userId, ?string $resumeId, string $coverNote): ExtractedText
    {
        if ($resumeId !== null && $resumeId !== '') {
            $t = $this->resumes->text($userId, $resumeId);
            if (trim($t['text']) !== '') {
                return new ExtractedText($t['text'], $t['confidence'], $t['parser']);
            }
        }
        // Fallback: the cover note is all we have (low confidence, never crashes).
        return new ExtractedText(trim($coverNote), $coverNote !== '' ? 30 : 0, 'none');
    }

    /**
     * @param  list<string>  $links
     * @return array{0: list<array<string,mixed>>, 1: list<array{score: ?int, reachable: bool}>}
     */
    private function probeSocial(array $links, JobProfile $job): array
    {
        $links = array_values(array_filter(array_map('trim', $links), static fn (string $l): bool => $l !== ''));
        if ($this->social === null || $links === []) {
            return [[], []];
        }

        try {
            $snapshots = $this->social->probe($links);
        } catch (Throwable) {
            return [[], []];
        }

        $scored = [];
        foreach ($snapshots as $i => $snapshot) {
            $verdict = SocialRelevanceScorer::score($snapshot, $job);
            $snapshots[$i]['_score'] = $verdict['score'];
            $snapshots[$i]['_matched'] = $verdict['matched'];
            $scored[] = ['score' => $verdict['score'], 'reachable' => $verdict['reachable']];
        }

        return [$snapshots, $scored];
    }

    /**
     * @param  list<array<string,mixed>>  $snapshots
     * @param  list<array{score: ?int, reachable: bool}>  $scored
     * @param  array{social_score: ?int, boost: int, scored_sources: int}  $roll
     */
    private function persist(
        string $workspaceId,
        string $applicationId,
        string $candidateUserId,
        string $jobId,
        ?string $resumeId,
        ExtractedText $parsed,
        ParsedResume $resume,
        ResumeAnalysisResult $r,
        FirstImpressionScore $fi,
        array $snapshots,
        array $scored,
        array $roll,
        int $socialConfidence,
        array $insights = [],
    ): string {
        $reportId = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');

        $this->connection->transaction(function () use (
            $reportId, $workspaceId, $applicationId, $candidateUserId, $jobId, $resumeId,
            $parsed, $resume, $r, $fi, $snapshots, $scored, $roll, $socialConfidence, $now, $insights
        ): void {
            // --- report ---
            $this->connection->statement(
                'INSERT INTO first_impression_reports
                  (id, workspace_id, application_id, candidate_user_id, job_id, resume_id, overall_score, core_score, resume_score, job_match_score, social_score, social_boost, threshold, passed, decision, confidence, engine_version, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $reportId, $workspaceId, $applicationId !== '' ? $applicationId : null, $candidateUserId, $jobId, $resumeId,
                    $fi->overall, $fi->core, $fi->resumeScore, $fi->jobMatchScore, $fi->socialScore, $fi->socialBoost,
                    $fi->threshold, $fi->passed ? 1 : 0, $fi->decision(), $fi->confidence, ResumeAnalysisEngine::VERSION, $now, $now,
                ],
            );

            // --- resume_analysis ---
            $analysisId = Ulid::generate();
            $s = $r->subScores;
            $this->connection->statement(
                'INSERT INTO resume_analysis
                  (id, workspace_id, report_id, resume_id, parser, parse_confidence, text_length, score, completeness, experience_match, skill_match, education_match, language_match, seniority_match, keyword_density, employment_stability, formatting_quality, missing_sections_count, confidence, years_experience, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $analysisId, $workspaceId, $reportId, $resumeId, $parsed->parser, $parsed->confidence, mb_strlen($parsed->text), $r->resumeScore,
                    $s['completeness'] ?? 0, $s['experience_match'] ?? 0, $s['skill_match'] ?? 0, $s['education_match'] ?? 0, $s['language_match'] ?? 0,
                    $s['seniority_match'] ?? 0, $s['keyword_density'] ?? 0, $s['employment_stability'] ?? 0, $s['formatting_quality'] ?? 0,
                    count($r->missingSections), $r->confidence, $r->yearsExperience, $now,
                ],
            );

            // --- resume_analysis_details (normalised evidence + structured data) ---
            $this->insertDetails($workspaceId, $analysisId, $reportId, $now, [
                'skill_matched' => $r->matchedSkills,
                'skill_missing' => $r->missingSkills,
                'keyword_hit' => $r->keywordHits,
                'strength' => $r->strengths,
                'weakness' => $r->weaknesses,
                'recommendation' => $r->recommendations,
                'rule_match' => $r->ruleMatches,
                'rule_fail' => $r->ruleFailures,
                'section_present' => $resume->presentSections(),
                'section_missing' => $resume->missingSections(),
                'title' => $resume->jobTitles,
                'company' => $resume->companies,
                'education' => $resume->education,
                'certification' => $resume->certifications,
                'language' => $resume->languages,
                'project' => $resume->projects,
                'publication' => $resume->publications,
                'award' => $resume->awards,
                'link' => $resume->links,
                // --- Candidate Intelligence (advisory, zero-AI) ---
                ...$this->insightDetailGroups($insights),
            ]);

            // --- social (only when links were probed) ---
            if ($snapshots !== []) {
                $socialId = Ulid::generate();
                $reachable = count(array_filter($snapshots, static fn (array $sn): bool => (bool) ($sn['reachable'] ?? false)));
                $signalCount = array_sum(array_map(static fn (array $sn): int => count((array) ($sn['signals'] ?? [])), $snapshots));
                $this->connection->statement(
                    'INSERT INTO social_analysis (id, workspace_id, report_id, score, boost, sources_total, sources_reachable, signals_count, confidence, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$socialId, $workspaceId, $reportId, $roll['social_score'], $roll['boost'], count($snapshots), $reachable, $signalCount, $socialConfidence, $now],
                );

                foreach ($snapshots as $sn) {
                    $snapId = Ulid::generate();
                    $this->connection->statement(
                        'INSERT INTO social_profiles_snapshot (id, workspace_id, social_analysis_id, report_id, platform, url, fetched, reachable, score, summary, error, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [
                            $snapId, $workspaceId, $socialId, $reportId, (string) ($sn['platform'] ?? 'other'), (string) ($sn['url'] ?? ''),
                            ! empty($sn['fetched']) ? 1 : 0, ! empty($sn['reachable']) ? 1 : 0, $sn['_score'] ?? null,
                            isset($sn['summary']) ? mb_substr((string) $sn['summary'], 0, 480) : null,
                            isset($sn['error']) && $sn['error'] !== null ? mb_substr((string) $sn['error'], 0, 240) : null, $now,
                        ],
                    );
                    foreach ((array) ($sn['signals'] ?? []) as $sig) {
                        if (! is_array($sig) || empty($sig['key'])) {
                            continue;
                        }
                        $this->connection->statement(
                            'INSERT INTO social_signals_snapshot (id, workspace_id, snapshot_id, signal_key, string_value, numeric_value, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?)',
                            [
                                Ulid::generate(), $workspaceId, $snapId, mb_substr((string) $sig['key'], 0, 64),
                                isset($sig['string_value']) && $sig['string_value'] !== null ? mb_substr((string) $sig['string_value'], 0, 500) : null,
                                isset($sig['numeric_value']) && $sig['numeric_value'] !== null ? (int) $sig['numeric_value'] : null, $now,
                            ],
                        );
                    }
                }
            }
        });

        return $reportId;
    }

    /**
     * Flatten the Candidate Intelligence derivation into normalised
     * resume_analysis_details groups (kind => list<string> labels). Advisory
     * only — these rows never affect the score; existing readers ignore unknown
     * kinds, so this is fully backward-compatible.
     *
     * @param  array<string, mixed>  $insights
     * @return array<string, list<string>>
     */
    private function insightDetailGroups(array $insights): array
    {
        if ($insights === []) {
            return [];
        }

        $groups = [];

        $progression = $insights['career_progression'] ?? null;
        if (is_array($progression) && ($progression['trajectory'] ?? 'insufficient') !== 'insufficient') {
            $groups['insight_progression'] = [(string) ($progression['label'] ?? '')];
        }

        $seniority = $insights['seniority'] ?? null;
        if (is_array($seniority) && (int) ($seniority['rank'] ?? 0) > 0) {
            $groups['insight_seniority'] = [(string) ($seniority['label'] ?? '')];
        }

        if (! empty($insights['leadership']) && is_array($insights['leadership'])) {
            $groups['insight_leadership'] = array_values(array_map('strval', $insights['leadership']));
        }

        if (! empty($insights['industries']) && is_array($insights['industries'])) {
            $groups['insight_industry'] = array_values(array_map('strval', $insights['industries']));
        }

        $stacks = $insights['tech_stacks'] ?? [];
        if (is_array($stacks) && $stacks !== []) {
            $rows = [];
            foreach ($stacks as $family => $skills) {
                $rows[] = $family . ': ' . implode(', ', array_slice((array) $skills, 0, 8));
            }
            $groups['insight_stack'] = $rows;
        }

        $stability = $insights['stability'] ?? null;
        if (is_array($stability) && (int) ($stability['avg_months'] ?? 0) > 0) {
            $groups['insight_stability'] = [(string) ($stability['label'] ?? '')];
        }

        if (! empty($insights['consistency']) && is_array($insights['consistency'])) {
            $groups['insight_consistency'] = array_values(array_map('strval', $insights['consistency']));
        }

        $gaps = $insights['skill_gaps'] ?? [];
        if (is_array($gaps) && $gaps !== []) {
            $groups['insight_skill_gap'] = array_values(array_map(
                static fn (array $g): string => (string) ($g['skill'] ?? '') . ' (' . (string) ($g['severity'] ?? 'core') . ')',
                $gaps,
            ));
        }

        $portfolio = $insights['portfolio'] ?? null;
        if (is_array($portfolio) && ! empty($portfolio['sources'])) {
            $groups['insight_portfolio'] = [(string) ($portfolio['label'] ?? '') . ' — ' . implode(', ', array_map('strval', $portfolio['sources']))];
        }

        $resumeQuality = $insights['resume_quality'] ?? null;
        if (is_array($resumeQuality) && ($resumeQuality['label'] ?? '') !== '') {
            $groups['insight_resume_quality'] = [(string) $resumeQuality['label']];
        }

        if (! empty($insights['highlights']) && is_array($insights['highlights'])) {
            $groups['insight_highlight'] = array_values(array_map('strval', $insights['highlights']));
        }

        return $groups;
    }

    /**
     * @param  array<string, list<string>>  $groups  kind => values
     */
    private function insertDetails(string $workspaceId, string $analysisId, string $reportId, string $now, array $groups): void
    {
        foreach ($groups as $kind => $values) {
            $position = 0;
            foreach ($values as $value) {
                $label = trim((string) $value);
                if ($label === '') {
                    continue;
                }
                $this->connection->statement(
                    'INSERT INTO resume_analysis_details (id, workspace_id, analysis_id, report_id, kind, label, value, score, position, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [Ulid::generate(), $workspaceId, $analysisId, $reportId, $kind, mb_substr($label, 0, 255), null, null, $position++, $now],
                );
            }
        }
    }

    private function emit(string $workspaceId, string $candidateUserId, string $applicationId, string $jobId, string $reportId, FirstImpressionScore $fi): void
    {
        $payload = [
            'workspace_id' => $workspaceId,
            'user_id' => $candidateUserId,
            'candidate_user_id' => $candidateUserId,
            'application_id' => $applicationId !== '' ? $applicationId : null,
            'job_id' => $jobId,
            'report_id' => $reportId,
            'overall_score' => $fi->overall,
            'threshold' => $fi->threshold,
            'passed' => $fi->passed,
            'decision' => $fi->decision(),
        ];
        $this->events->dispatch('first_impression.completed', $payload);
        $this->events->dispatch($fi->passed ? 'first_impression.passed' : 'first_impression.failed', $payload);
    }
}
