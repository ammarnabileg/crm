<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Domain\FirstImpression;

use HaHireAI\Modules\Recruitment\Domain\Resume\ParsedResume;
use HaHireAI\Modules\Recruitment\Domain\SkillOntology;

/**
 * Engine 1b — Candidate Intelligence. A pure, deterministic, ZERO-AI enrichment
 * layer that derives qualitative intelligence ABOUT a candidate from the parsed
 * résumé (and, where it adds relevance, the job). It is **additive and advisory**:
 * it never changes the First Impression score or the decision — it only produces
 * normalised, explainable insight rows that HR sees on the report.
 *
 * Derivations (all rule-based):
 *   - career progression (trajectory of seniority across roles)
 *   - leadership indicators (verbs/cues of leading, managing, mentoring)
 *   - industry detection (domains the candidate has worked in)
 *   - technical stack detection (which stack families the skills cluster into)
 *   - seniority detection (inferred level, in words)
 *   - employment stability (avg tenure, in words)
 *   - consistency analysis (claims that don't line up — soft flags, never harsh)
 *   - skill-gap analysis (core gaps vs the job, with severity)
 *
 * Pure value-free output: an associative array the orchestrator maps to
 * resume_analysis_details rows (kind = insight_*). No I/O, no provider, no DB.
 */
final class CandidateInsights
{
    /**
     * @return array{
     *   career_progression: array{trajectory: string, label: string, steps: list<string>},
     *   seniority: array{rank: int, label: string},
     *   leadership: list<string>,
     *   industries: list<string>,
     *   tech_stacks: array<string, list<string>>,
     *   primary_stack: ?string,
     *   stability: array{avg_months: int, label: string},
     *   consistency: list<string>,
     *   skill_gaps: list<array{skill: string, severity: string}>,
     *   highlights: list<string>
     * }
     */
    public static function derive(ParsedResume $resume, JobProfile $job): array
    {
        $haystack = $resume->searchableText();

        $progression = self::careerProgression($resume);
        $seniority = self::seniority($resume);
        $leadership = SkillOntology::leadershipSignals($resume->rawText);
        $industries = SkillOntology::detectIndustries($haystack);
        $stacks = SkillOntology::detectStacks($resume->skills);
        $primaryStack = $stacks === [] ? null : (string) array_key_first($stacks);
        $stability = self::stability($resume);
        $consistency = self::consistency($resume);
        $skillGaps = self::skillGaps($resume, $job, $haystack);

        return [
            'career_progression' => $progression,
            'seniority' => $seniority,
            'leadership' => $leadership,
            'industries' => $industries,
            'tech_stacks' => $stacks,
            'primary_stack' => $primaryStack,
            'stability' => $stability,
            'consistency' => $consistency,
            'skill_gaps' => $skillGaps,
            'highlights' => self::highlights($progression, $seniority, $leadership, $industries, $primaryStack, $stability),
        ];
    }

    /**
     * Read the trajectory of seniority across the (chronological) experience list.
     * The structurer lists roles top-down (most recent first), so a rising rank
     * from bottom→top is upward growth.
     *
     * @return array{trajectory: string, label: string, steps: list<string>}
     */
    private static function careerProgression(ParsedResume $resume): array
    {
        $titles = $resume->jobTitles;
        if (count($titles) < 2) {
            return ['trajectory' => 'insufficient', 'label' => 'Not enough role history to assess progression', 'steps' => $titles];
        }

        // Rank each title; evaluate oldest → newest (reverse of the top-down list).
        $ranks = [];
        foreach (array_reverse($titles) as $title) {
            $ranks[] = SkillOntology::inferSeniority($title);
        }
        $ups = 0;
        $downs = 0;
        for ($i = 1, $n = count($ranks); $i < $n; $i++) {
            if ($ranks[$i] > $ranks[$i - 1]) {
                $ups++;
            } elseif ($ranks[$i] < $ranks[$i - 1]) {
                $downs++;
            }
        }

        [$trajectory, $label] = match (true) {
            $ups > 0 && $downs === 0 => ['upward', 'Clear upward career progression'],
            $ups > $downs => ['mostly_upward', 'Generally upward progression'],
            $ups === 0 && $downs === 0 => ['lateral', 'Steady / lateral career path'],
            $downs > $ups => ['downward', 'Recent step(s) down in seniority'],
            default => ['mixed', 'Mixed career trajectory'],
        };

        return ['trajectory' => $trajectory, 'label' => $label, 'steps' => array_values(array_reverse($titles))];
    }

    /** @return array{rank: int, label: string} */
    private static function seniority(ParsedResume $resume): array
    {
        $rank = max(
            SkillOntology::inferSeniority(implode(' ', $resume->jobTitles) . ' ' . ((string) $resume->summary)),
            self::rankFromYears($resume->yearsExperience),
        );
        $label = match (true) {
            $rank >= 9 => 'Executive',
            $rank >= 8 => 'Director',
            $rank >= 7 => 'Manager',
            $rank >= 6 => 'Lead / Principal',
            $rank >= 5 => 'Senior',
            $rank >= 4 => 'Mid-level',
            $rank >= 2 => 'Junior',
            $rank === 1 => 'Intern / Trainee',
            default => 'Unspecified',
        };

        return ['rank' => $rank, 'label' => $label];
    }

    private static function rankFromYears(?int $years): int
    {
        return match (true) {
            $years === null => 0,
            $years <= 1 => 2,
            $years <= 3 => 4,
            $years <= 6 => 5,
            $years <= 10 => 6,
            default => 7,
        };
    }

    /** @return array{avg_months: int, label: string} */
    private static function stability(ParsedResume $resume): array
    {
        $tenures = [];
        foreach ($resume->experiences as $e) {
            $m = (int) ($e['months'] ?? 0);
            if ($m > 0) {
                $tenures[] = $m;
            }
        }
        if ($tenures === []) {
            return ['avg_months' => 0, 'label' => 'Tenure not determinable'];
        }
        $avg = (int) round(array_sum($tenures) / count($tenures));
        $label = match (true) {
            $avg >= 36 => 'Very stable (avg ' . self::ym($avg) . ' per role)',
            $avg >= 24 => 'Stable (avg ' . self::ym($avg) . ' per role)',
            $avg >= 18 => 'Moderately stable (avg ' . self::ym($avg) . ' per role)',
            $avg >= 12 => 'Some short tenures (avg ' . self::ym($avg) . ' per role)',
            default => 'Frequent moves (avg ' . self::ym($avg) . ' per role)',
        };

        return ['avg_months' => $avg, 'label' => $label];
    }

    private static function ym(int $months): string
    {
        $y = intdiv($months, 12);
        $m = $months % 12;
        if ($y > 0 && $m > 0) {
            return $y . 'y ' . $m . 'm';
        }

        return $y > 0 ? $y . 'y' : $m . 'm';
    }

    /**
     * Soft consistency checks — surfaced as advisory flags, never a penalty.
     *
     * @return list<string>
     */
    private static function consistency(ParsedResume $resume): array
    {
        $flags = [];

        // Claimed years vs the sum of detected tenures.
        $claimed = $resume->yearsExperience;
        $sumMonths = 0;
        foreach ($resume->experiences as $e) {
            $sumMonths += (int) ($e['months'] ?? 0);
        }
        $computedYears = intdiv($sumMonths, 12);
        if ($claimed !== null && $computedYears > 0 && abs($claimed - $computedYears) >= 3) {
            $flags[] = sprintf(
                'Stated experience (%d yrs) differs from the dated roles (~%d yrs) — worth confirming.',
                $claimed,
                $computedYears,
            );
        }

        // Skills named in the summary but absent from a dedicated skills list.
        if ($resume->summary !== null && $resume->skills === [] && SkillOntology::detectSkills((string) $resume->summary) !== []) {
            $flags[] = 'Skills appear in the summary but there is no dedicated skills section.';
        }

        // Contact completeness.
        if ($resume->email === null) {
            $flags[] = 'No email address detected on the résumé.';
        }

        // A senior-sounding title with very little detectable history.
        $rank = SkillOntology::inferSeniority(implode(' ', $resume->jobTitles));
        if ($rank >= 6 && count($resume->experiences) <= 1) {
            $flags[] = 'Senior/lead title with limited dated work history to support it.';
        }

        return $flags;
    }

    /**
     * Skill-gap analysis vs the job: which required skills are missing and how
     * critical each gap is. "core" = named in the job's required skills.
     *
     * @return list<array{skill: string, severity: string}>
     */
    private static function skillGaps(ParsedResume $resume, JobProfile $job, string $haystack): array
    {
        $gaps = [];
        foreach ($job->requiredSkills as $skill) {
            if (! SkillOntology::skillPresent($skill, $haystack)) {
                // Severity by how central the skill is to the role title.
                $canonical = SkillOntology::canonical($skill);
                $inTitle = $job->title !== '' && SkillOntology::skillPresent($skill, $job->title);
                $gaps[] = ['skill' => $canonical, 'severity' => $inTitle ? 'critical' : 'core'];
            }
        }

        return $gaps;
    }

    /**
     * @param  array{trajectory: string, label: string, steps: list<string>}  $progression
     * @param  array{rank: int, label: string}  $seniority
     * @param  list<string>  $leadership
     * @param  list<string>  $industries
     * @param  array{avg_months: int, label: string}  $stability
     * @return list<string>
     */
    private static function highlights(array $progression, array $seniority, array $leadership, array $industries, ?string $primaryStack, array $stability): array
    {
        $out = [];
        if ($seniority['rank'] > 0) {
            $out[] = $seniority['label'] . ' profile';
        }
        if ($primaryStack !== null) {
            $out[] = 'Primary stack: ' . $primaryStack;
        }
        if (in_array($progression['trajectory'], ['upward', 'mostly_upward'], true)) {
            $out[] = $progression['label'];
        }
        if ($leadership !== []) {
            $out[] = 'Leadership signals: ' . count($leadership);
        }
        if ($industries !== []) {
            $out[] = 'Industry: ' . implode(', ', array_slice($industries, 0, 2));
        }
        if ($stability['avg_months'] >= 24) {
            $out[] = $stability['label'];
        }

        return $out;
    }
}
