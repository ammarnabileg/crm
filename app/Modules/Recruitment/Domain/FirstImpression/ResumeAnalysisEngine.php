<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Domain\FirstImpression;

use HaHireAI\Modules\Recruitment\Domain\Resume\ParsedResume;
use HaHireAI\Modules\Recruitment\Domain\SkillOntology;

/**
 * Engine 1 — the Resume Analysis Engine. A deterministic, NO-AI, multi-signal
 * scorer that measures how CLOSE a résumé is to a job (the basis of the whole
 * First Impression gate) and how strong the résumé is on its own. It consumes
 * ONLY a {@see ParsedResume} (text + structured fields) and a {@see JobProfile};
 * it never sees a file, a provider, or the database.
 *
 * It produces, on a 0..100 scale:
 *   - job_match_score  : weighted blend of the job-aware sub-scores (the "basis")
 *   - quality_score    : weighted blend of the job-agnostic sub-scores
 *   - resume_score     : 0.70·job_match + 0.30·quality  (Engine 1 headline)
 * plus every sub-score and the normalised evidence behind them.
 *
 * Pure, deterministic, exhaustively unit-testable (docs/FIRST_IMPRESSION_ENGINE.md §3).
 */
final class ResumeAnalysisEngine
{
    /** Relevance (job-aware) sub-score weights — sum to 100. */
    private const RELEVANCE_WEIGHTS = [
        'skill_match' => 40,
        'experience_match' => 20,
        'seniority_match' => 15,
        'keyword_density' => 12,
        'education_match' => 8,
        'language_match' => 5,
    ];

    /** Quality (job-agnostic) sub-score weights — sum to 100. */
    private const QUALITY_WEIGHTS = [
        'completeness' => 45,
        'formatting_quality' => 30,
        'employment_stability' => 25,
    ];

    /** Completeness section weights — sum to 100. */
    private const SECTION_WEIGHTS = [
        'contact' => 20, 'summary' => 10, 'experience' => 25, 'education' => 10,
        'skills' => 20, 'certifications' => 5, 'languages' => 5, 'projects' => 5,
    ];

    public const VERSION = 'fi-1.0';

    public function analyze(ParsedResume $resume, JobProfile $job): ResumeAnalysisResult
    {
        $strengths = [];
        $weaknesses = [];
        $recommendations = [];
        $ruleMatches = [];
        $ruleFailures = [];

        $haystack = $resume->searchableText();

        // --- RELEVANCE sub-scores (job-aware) ---------------------------------
        [$skillMatch, $matched, $missing] = $this->skillMatch($resume, $job, $haystack);
        $experienceMatch = $this->experienceMatch($resume, $job, $ruleMatches, $ruleFailures);
        $seniorityMatch = $this->seniorityMatch($resume, $job);
        [$keywordDensity, $keywordHits] = $this->keywordDensity($job, $haystack);
        $educationMatch = $this->educationMatch($resume, $job, $haystack);
        $languageMatch = $this->languageMatch($resume, $job, $haystack);

        // --- QUALITY sub-scores (job-agnostic) --------------------------------
        $completeness = $this->completeness($resume);
        $employmentStability = $this->employmentStability($resume);
        $formattingQuality = $this->formattingQuality($resume);

        $subScores = [
            'skill_match' => $skillMatch,
            'experience_match' => $experienceMatch,
            'seniority_match' => $seniorityMatch,
            'keyword_density' => $keywordDensity,
            'education_match' => $educationMatch,
            'language_match' => $languageMatch,
            'completeness' => $completeness,
            'employment_stability' => $employmentStability,
            'formatting_quality' => $formattingQuality,
        ];

        $jobMatch = $this->weighted($subScores, self::RELEVANCE_WEIGHTS);
        $quality = $this->weighted($subScores, self::QUALITY_WEIGHTS);
        $resumeScore = (int) round(0.70 * $jobMatch + 0.30 * $quality);

        // --- Evidence ---------------------------------------------------------
        $this->buildSkillEvidence($matched, $missing, $strengths, $weaknesses, $recommendations, $ruleMatches, $ruleFailures);
        $this->buildSubScoreEvidence($subScores, $strengths, $weaknesses, $recommendations);

        $missingSections = $resume->missingSections();
        if ($missingSections !== []) {
            $recommendations[] = 'Add the missing résumé section(s): ' . implode(', ', $missingSections) . '.';
        }

        $confidence = $this->confidence($resume, $matched, $missing);

        return new ResumeAnalysisResult(
            resumeScore: $resumeScore,
            jobMatchScore: $jobMatch,
            qualityScore: $quality,
            confidence: $confidence,
            yearsExperience: $resume->yearsExperience,
            subScores: $subScores,
            matchedSkills: $matched,
            missingSkills: $missing,
            keywordHits: $keywordHits,
            strengths: array_values(array_unique($strengths)),
            weaknesses: array_values(array_unique($weaknesses)),
            recommendations: array_values(array_unique($recommendations)),
            ruleMatches: array_values(array_unique($ruleMatches)),
            ruleFailures: array_values(array_unique($ruleFailures)),
            missingSections: $missingSections,
        );
    }

    /**
     * @return array{0:int,1:list<string>,2:list<string>} [score, matched, missing]
     */
    private function skillMatch(ParsedResume $resume, JobProfile $job, string $haystack): array
    {
        $required = $job->requiredSkills;
        if ($required === []) {
            // No explicit requirement: reward the breadth of relevant skills found.
            $count = count($resume->skills);
            $score = $count >= 8 ? 85 : ($count >= 4 ? 72 : ($count >= 1 ? 60 : 45));

            return [$score, array_slice($resume->skills, 0, 15), []];
        }

        $matched = [];
        $missing = [];
        foreach ($required as $skill) {
            if (SkillOntology::skillPresent($skill, $haystack)) {
                $matched[] = SkillOntology::canonical($skill);
            } else {
                $missing[] = SkillOntology::canonical($skill);
            }
        }
        $ratio = count($matched) / max(1, count($required));
        // A small breadth bonus for extra relevant skills beyond the requirement.
        $extra = max(0, count($resume->skills) - count($matched));
        $bonus = min(8, $extra);
        $score = (int) min(100, round($ratio * 100) + ($ratio > 0 ? $bonus : 0));

        return [$score, $matched, $missing];
    }

    /**
     * @param  list<string>  $ruleMatches
     * @param  list<string>  $ruleFailures
     */
    private function experienceMatch(ParsedResume $resume, JobProfile $job, array &$ruleMatches, array &$ruleFailures): int
    {
        $years = $resume->yearsExperience;
        $min = $job->experienceMin;
        $max = $job->experienceMax;

        if ($min === null && $max === null) {
            return $years !== null ? 78 : 60; // no requirement to measure against
        }
        if ($years === null) {
            $ruleFailures[] = 'Years of experience could not be determined from the résumé.';

            return 55;
        }

        $min ??= 0;
        if ($years >= $min && ($max === null || $years <= $max + 2)) {
            $ruleMatches[] = sprintf('Meets the experience requirement (%d yrs ≥ %d).', $years, $min);

            return 100;
        }
        if ($max !== null && $years > $max + 2) {
            // Overqualified — a mild, not harsh, reduction.
            return 82;
        }
        // Under the minimum: scale by how close they are.
        $ratio = $min > 0 ? $years / $min : 1.0;
        $ruleFailures[] = sprintf('Below the minimum experience (%d of %d yrs).', $years, $min);

        return (int) max(0, min(95, round($ratio * 95)));
    }

    private function seniorityMatch(ParsedResume $resume, JobProfile $job): int
    {
        $jobRank = $job->seniorityRank();
        if ($jobRank === 0) {
            return 76; // job seniority not specified
        }
        $candRank = max(
            SkillOntology::inferSeniority(implode(' ', $resume->jobTitles) . ' ' . ((string) $resume->summary)),
            $this->seniorityFromYears($resume->yearsExperience),
        );
        if ($candRank === 0) {
            return 62;
        }
        $diff = $candRank - $jobRank;

        return match (true) {
            $diff === 0 => 100,
            abs($diff) === 1 => 86,
            $diff >= 2 => 80,            // overqualified — mild
            $diff === -2 => 58,
            default => 38,               // far below (-3 or more)
        };
    }

    private function seniorityFromYears(?int $years): int
    {
        return match (true) {
            $years === null => 0,
            $years <= 1 => 2,   // junior
            $years <= 3 => 4,   // mid
            $years <= 6 => 5,   // senior
            $years <= 10 => 6,  // lead/principal
            default => 7,       // manager+
        };
    }

    /**
     * @return array{0:int,1:list<string>} [score, hits]
     */
    private function keywordDensity(JobProfile $job, string $haystack): array
    {
        $keywords = $job->keywords;
        if ($keywords === []) {
            return [70, []]; // no HR keywords set — neutral
        }
        $hits = [];
        foreach ($keywords as $kw) {
            if ($kw !== '' && mb_strpos($haystack, mb_strtolower($kw)) !== false) {
                $hits[] = $kw;
            }
        }
        $ratio = count($hits) / max(1, count($keywords));

        return [(int) round($ratio * 100), $hits];
    }

    private function educationMatch(ParsedResume $resume, JobProfile $job, string $haystack): int
    {
        $level = SkillOntology::detectEducationLevel($haystack);
        $candRank = $level !== null ? (SkillOntology::EDUCATION_RANK[$level] ?? 0) : 0;
        // Desired level inferred from job seniority (soft, lenient).
        $jobRank = $job->seniorityRank();
        $desired = match (true) {
            $jobRank >= 8 => 4, // director+/exec → master preferred
            $jobRank >= 4 => 3, // mid/senior+ → bachelor preferred
            default => 0,       // junior/intern → no degree pressure
        };

        if ($desired === 0) {
            return $candRank > 0 ? 90 : 70; // degree is a bonus, never required here
        }
        if ($candRank === 0) {
            return 55; // no degree detected, but never harshly penalised
        }

        return $candRank >= $desired ? 100 : 72;
    }

    private function languageMatch(ParsedResume $resume, JobProfile $job, string $haystack): int
    {
        // Languages the job explicitly asks for (only if named in keywords/skills).
        $desired = [];
        foreach (array_merge($job->keywords, $job->requiredSkills) as $term) {
            foreach (SkillOntology::LANGUAGES as $canonical => $aliases) {
                if (in_array(mb_strtolower(trim($term)), array_map('mb_strtolower', array_merge([$canonical], $aliases)), true)) {
                    $desired[$canonical] = true;
                }
            }
        }
        $desired = array_keys($desired);
        if ($desired === []) {
            return 80; // no specific language requirement
        }
        $have = SkillOntology::detectLanguages($haystack);
        $met = count(array_intersect($desired, $have));

        return (int) round($met / max(1, count($desired)) * 100);
    }

    private function completeness(ParsedResume $resume): int
    {
        $present = $resume->presentSections();
        $score = 0;
        foreach (self::SECTION_WEIGHTS as $section => $weight) {
            if (in_array($section, $present, true)) {
                $score += $weight;
            }
        }
        // Name + email presence is a small extra signal of a usable CV.
        if ($resume->name !== null && $resume->email !== null) {
            $score = min(100, $score + 0);
        }

        return min(100, $score);
    }

    private function employmentStability(ParsedResume $resume): int
    {
        $tenures = [];
        foreach ($resume->experiences as $e) {
            $m = (int) ($e['months'] ?? 0);
            if ($m > 0) {
                $tenures[] = $m;
            }
        }
        if (count($tenures) === 0) {
            return 62; // not enough data — neutral
        }
        $avg = array_sum($tenures) / count($tenures);
        $base = match (true) {
            $avg >= 36 => 100,
            $avg >= 24 => 90,
            $avg >= 18 => 78,
            $avg >= 12 => 64,
            $avg >= 6 => 48,
            default => 32,
        };
        // Penalise excessive short-stint job-hopping a little.
        $shortStints = count(array_filter($tenures, static fn (int $m): bool => $m < 12));
        if ($shortStints >= 3) {
            $base = max(20, $base - 12);
        }

        return $base;
    }

    private function formattingQuality(ParsedResume $resume): int
    {
        $len = mb_strlen($resume->rawText);
        $lengthScore = match (true) {
            $len >= 1500 && $len <= 9000 => 100,
            $len >= 600 && $len < 1500 => 80,
            $len > 9000 && $len <= 15000 => 78,
            $len >= 200 && $len < 600 => 55,
            default => 30,
        };
        $structureScore = min(100, count($resume->presentSections()) * 14);
        $contactScore = ($resume->email !== null ? 50 : 0) + ($resume->phone !== null ? 30 : 0) + ($resume->links !== [] ? 20 : 0);

        // Parse confidence anchors the whole thing (a scanned PDF is penalised).
        $raw = 0.35 * $resume->parseConfidence + 0.30 * $lengthScore + 0.20 * $structureScore + 0.15 * $contactScore;

        return (int) round(min(100, $raw));
    }

    /**
     * @param  list<string>  $matched
     * @param  list<string>  $missing
     */
    private function confidence(ParsedResume $resume, array $matched, array $missing): int
    {
        $structure = min(100, count($resume->presentSections()) * 13);
        $dataPoints = 0;
        $dataPoints += $resume->email !== null ? 10 : 0;
        $dataPoints += $resume->skills !== [] ? 25 : 0;
        $dataPoints += $resume->experiences !== [] ? 25 : 0;
        $dataPoints += $resume->yearsExperience !== null ? 20 : 0;
        $dataPoints += ($matched !== [] || $missing !== []) ? 20 : 0;

        $raw = 0.5 * $resume->parseConfidence + 0.25 * $structure + 0.25 * min(100, $dataPoints);

        return (int) round(min(100, $raw));
    }

    /**
     * @param  list<string>  $matched
     * @param  list<string>  $missing
     * @param  list<string>  $strengths
     * @param  list<string>  $weaknesses
     * @param  list<string>  $recommendations
     * @param  list<string>  $ruleMatches
     * @param  list<string>  $ruleFailures
     */
    private function buildSkillEvidence(array $matched, array $missing, array &$strengths, array &$weaknesses, array &$recommendations, array &$ruleMatches, array &$ruleFailures): void
    {
        if ($matched !== []) {
            $strengths[] = 'Matches required skills: ' . implode(', ', array_slice($matched, 0, 8)) . '.';
            $ruleMatches[] = count($matched) . ' required skill(s) matched.';
        }
        if ($missing !== []) {
            $weaknesses[] = 'Missing required skills: ' . implode(', ', array_slice($missing, 0, 8)) . '.';
            $recommendations[] = 'Strengthen or surface these skills on the CV: ' . implode(', ', array_slice($missing, 0, 8)) . '.';
            $ruleFailures[] = count($missing) . ' required skill(s) not found.';
        }
    }

    /**
     * @param  array<string, int>  $subScores
     * @param  list<string>  $strengths
     * @param  list<string>  $weaknesses
     * @param  list<string>  $recommendations
     */
    private function buildSubScoreEvidence(array $subScores, array &$strengths, array &$weaknesses, array &$recommendations): void
    {
        $labels = [
            'experience_match' => 'experience match',
            'seniority_match' => 'seniority fit',
            'education_match' => 'education fit',
            'employment_stability' => 'employment stability',
            'completeness' => 'résumé completeness',
            'keyword_density' => 'keyword relevance',
        ];
        foreach ($labels as $key => $label) {
            $v = $subScores[$key] ?? 0;
            if ($v >= 85) {
                $strengths[] = 'Strong ' . $label . ' (' . $v . '%).';
            } elseif ($v < 50) {
                $weaknesses[] = 'Low ' . $label . ' (' . $v . '%).';
            }
        }
    }

    /**
     * @param  array<string, int>  $subScores
     * @param  array<string, int>  $weights
     */
    private function weighted(array $subScores, array $weights): int
    {
        $total = 0;
        $sum = 0;
        foreach ($weights as $key => $weight) {
            $sum += $weight * ($subScores[$key] ?? 0);
            $total += $weight;
        }

        return $total > 0 ? (int) round($sum / $total) : 0;
    }
}
