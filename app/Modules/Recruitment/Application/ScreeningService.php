<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Modules\AiEngine\Contracts\AiCapabilities;
use HaHireAI\Modules\Recruitment\Domain\CvScreening;

/**
 * The credit-saving screening decision, shared by both apply paths (public token
 * + in-portal). Decides whether the (paid) AI interview should run for a new
 * application, purely from the job's per-job configuration and the applicant's
 * own words — so platform/AI credits are never spent on a clearly-irrelevant or
 * screening-disabled application.
 *
 * Decision (matches the product spec):
 *   - ai_screening_enabled = 0            -> no AI interview (accept, handle manually)
 *   - workspace has no AI key configured  -> no AI interview (cannot run one)
 *   - keywords set but applicant no match  -> no AI interview (accept, save credits)
 *   - enabled + (no keywords OR a match)   -> run the AI interview
 */
final class ScreeningService
{
    public function __construct(
        private readonly CandidateProfileService $profiles,
        private readonly AiCapabilities $ai,
    ) {
    }

    /** @param array<string,mixed> $job */
    public function shouldRunAiInterview(string $workspaceId, array $job, string $userId, string $coverNote = ''): bool
    {
        if ((int) ($job['ai_screening_enabled'] ?? 1) === 0) {
            return false;
        }
        if (! $this->ai->interviewsEnabled($workspaceId)) {
            return false;
        }

        $keywords = CvScreening::keywords((string) ($job['screening_keywords'] ?? ''));
        if ($keywords === []) {
            return true;
        }

        return CvScreening::passes($this->screenText($workspaceId, $userId, $coverNote), $keywords);
    }

    /**
     * The applicant's words available at apply time: cover note + their workspace
     * CV profile (summary + structured skills/education/etc.) + name. Workspace-scoped.
     */
    private function screenText(string $workspaceId, string $userId, string $coverNote): string
    {
        $parts = [$coverNote];

        $profile = $this->profiles->profile($workspaceId, $userId);
        if ($profile !== null) {
            $parts[] = (string) ($profile['summary'] ?? '');
            $parts[] = (string) ($profile['name'] ?? '');
        }

        $details = $this->profiles->details($workspaceId, $userId);
        foreach (['skills', 'education', 'certifications', 'languages', 'location', 'summary'] as $key) {
            if (! empty($details[$key]) && is_scalar($details[$key])) {
                $parts[] = (string) $details[$key];
            }
        }

        return trim(implode(' ', array_filter($parts, static fn ($p): bool => trim((string) $p) !== '')));
    }
}
