<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Files\Application\FileService;

/**
 * Builds a candidate's activity Timeline within ONE workspace by merging events
 * already stored across the workspace's own data (applications, stage moves,
 * interviews, notes, files, offers). Pure aggregation — no new tables — and
 * strictly workspace-scoped, so a company only ever sees its own interaction
 * (docs/DOMAIN_MODEL.md, privacy isolation).
 */
final class CandidateTimelineService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly FileService $files,
    ) {
    }

    /**
     * @return list<array{at: string, type: string, label: string}> newest first
     */
    public function timeline(string $workspaceId, string $userId, string $profileId): array
    {
        $events = [];

        foreach ($this->connection->select(
            'SELECT a.applied_at AS at, j.title FROM applications a JOIN jobs j ON j.id = a.job_id
              WHERE a.workspace_id = ? AND a.user_id = ? AND a.deleted_at IS NULL',
            [$workspaceId, $userId],
        ) as $r) {
            $events[] = ['at' => (string) $r['at'], 'type' => 'application', 'label' => 'Applied to ' . (string) $r['title']];
        }

        foreach ($this->connection->select(
            'SELECT h.created_at AS at, s.name AS stage, j.title FROM application_stage_history h
               JOIN applications a ON a.id = h.application_id
               JOIN jobs j ON j.id = a.job_id
               LEFT JOIN pipeline_stages s ON s.id = h.to_stage_id
              WHERE h.workspace_id = ? AND a.user_id = ?',
            [$workspaceId, $userId],
        ) as $r) {
            $events[] = ['at' => (string) $r['at'], 'type' => 'stage', 'label' => 'Moved to ' . (string) ($r['stage'] ?? 'a stage') . ' · ' . (string) $r['title']];
        }

        foreach ($this->connection->select(
            'SELECT COALESCE(i.completed_at, i.created_at) AS at, i.type, i.status, i.score, j.title
               FROM interviews i JOIN jobs j ON j.id = i.job_id
              WHERE i.workspace_id = ? AND i.candidate_user_id = ? AND i.deleted_at IS NULL',
            [$workspaceId, $userId],
        ) as $r) {
            $score = $r['score'] !== null ? ' (score ' . (int) $r['score'] . ')' : '';
            $events[] = ['at' => (string) $r['at'], 'type' => 'interview', 'label' => ucfirst((string) $r['type']) . ' interview ' . (string) $r['status'] . ' · ' . (string) $r['title'] . $score];
        }

        foreach ($this->connection->select(
            'SELECT n.created_at AS at, u.name AS author FROM candidate_notes n
               LEFT JOIN users u ON u.id = n.author_user_id
              WHERE n.workspace_id = ? AND n.candidate_profile_id = ? AND n.deleted_at IS NULL',
            [$workspaceId, $profileId],
        ) as $r) {
            $events[] = ['at' => (string) $r['at'], 'type' => 'note', 'label' => 'Note added by ' . (string) ($r['author'] ?? 'system')];
        }

        foreach ($this->connection->select(
            'SELECT o.created_at AS at, o.title, o.status FROM offers o
               JOIN applications a ON a.id = o.application_id
              WHERE o.workspace_id = ? AND a.user_id = ? AND o.deleted_at IS NULL',
            [$workspaceId, $userId],
        ) as $r) {
            $events[] = ['at' => (string) $r['at'], 'type' => 'offer', 'label' => 'Offer ' . (string) $r['status'] . ': ' . (string) ($r['title'] ?: 'offer')];
        }

        // Files come through the Files service (cross-module data, never a direct table read).
        foreach ($this->files->listForEntity($workspaceId, 'candidate_profile', $profileId) as $f) {
            $events[] = ['at' => (string) $f['created_at'], 'type' => 'file', 'label' => 'File: ' . (string) $f['original_name']];
        }

        usort($events, static fn (array $a, array $b): int => strcmp((string) $b['at'], (string) $a['at']));

        return $events;
    }
}
