<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\AiEngine\Application\AiEngine;
use HaHireAI\Modules\Recruitment\Domain\SkillCatalog;

/**
 * Side-by-side candidate comparison + AI Q&A (recruitment spec #15). Gathers each
 * candidate's latest AI assessment in THIS workspace and can ask the AI Engine a
 * natural-language question across them. Advisory — humans decide.
 */
final class ComparisonService
{
    private const MAX = 4;

    public function __construct(
        private readonly Connection $connection,
        private readonly AssessmentService $assessments,
        private readonly AiEngine $ai,
    ) {
    }

    /**
     * @param  list<string>  $userIds
     * @return list<array{user_id:string,name:string,email:string,assessment:array<string,mixed>|null}>
     */
    public function gather(string $workspaceId, array $userIds): array
    {
        $out = [];
        foreach (array_slice(array_values(array_unique($userIds)), 0, self::MAX) as $userId) {
            // Only candidates that exist in THIS workspace (tenant isolation).
            $row = $this->connection->selectOne(
                'SELECT u.id, u.name, u.email FROM candidate_profiles cp JOIN users u ON u.id = cp.user_id
                  WHERE cp.workspace_id = ? AND cp.user_id = ?',
                [$workspaceId, $userId],
            );
            if ($row === null) {
                continue;
            }
            $out[] = [
                'user_id' => (string) $row['id'],
                'name' => (string) $row['name'],
                'email' => (string) $row['email'],
                'assessment' => $this->assessments->latestForCandidate($workspaceId, $userId),
            ];
        }

        return $out;
    }

    /**
     * Ask the AI a question across the gathered candidates.
     *
     * @param  list<string>  $userIds
     * @return array{answer: string, provider: string}|null
     */
    public function ask(string $workspaceId, array $userIds, string $question, ?string $actorUserId = null): ?array
    {
        $candidates = $this->gather($workspaceId, $userIds);
        if ($candidates === [] || trim($question) === '') {
            return null;
        }

        $lines = [];
        foreach ($candidates as $c) {
            $a = $c['assessment'];
            $skills = '';
            if ($a !== null && isset($a['skills']) && is_array($a['skills'])) {
                $parts = [];
                foreach ($a['skills'] as $key => $s) {
                    $parts[] = (SkillCatalog::SKILLS[$key]['label'] ?? $key) . ' ' . (int) ($s['score'] ?? 0);
                }
                $skills = implode(', ', $parts);
            }
            $lines[] = '- ' . $c['name'] . ': fit ' . ($a['fit_score'] ?? 'n/a') . '/100; ' . $skills;
        }

        $result = $this->ai->run($workspaceId, 'compare_candidates', [
            'candidates' => implode("\n", $lines),
            'question' => $question,
        ], $actorUserId);

        return ['answer' => $result->text, 'provider' => $result->provider];
    }
}
