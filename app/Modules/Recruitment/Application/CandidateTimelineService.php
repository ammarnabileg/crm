<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Core\Contracts\FileStorage;
use HaHireAI\Shared\Ulid;

/**
 * Builds a candidate's activity Timeline within ONE workspace by merging events
 * already stored across the workspace's own data (applications, stage moves,
 * interviews, notes, files, offers, learning) together with manual "mini-CRM"
 * entries a recruiter logs by hand (calls, messages, meetings, notes). Mostly
 * pure aggregation over data already collected; strictly workspace-scoped, so a
 * company only ever sees its own interaction (docs/DOMAIN_MODEL.md, privacy).
 */
final class CandidateTimelineService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly FileStorage $files,
    ) {
    }

    /**
     * Log a manual Timeline entry (the mini-CRM surface): free-form text, the date
     * it happened, and the account that recorded it. Workspace-scoped.
     */
    public function addEntry(
        string $workspaceId,
        string $userId,
        string $body,
        string $kind = 'update',
        ?string $occurredAt = null,
        ?string $createdBy = null,
    ): string {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO candidate_timeline_entries (id, workspace_id, user_id, kind, body, occurred_at, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $userId, $this->normalizeKind($kind), mb_substr($body, 0, 1000), $occurredAt ?: $now, $createdBy, $now],
        );

        return $id;
    }

    /**
     * @return list<array{at: string, type: string, label: string, by: string}> newest first
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
            $events[] = ['at' => (string) $r['at'], 'type' => 'note', 'label' => 'Note added', 'by' => (string) ($r['author'] ?? 'system')];
        }

        // Learning enrollments (onboarding / development) tied to this user.
        foreach ($this->connection->select(
            'SELECT COALESCE(e.completed_at, e.started_at, e.created_at) AS at, e.status, e.progress_percent, p.title
               FROM learning_enrollments e JOIN learning_programs p ON p.id = e.program_id
              WHERE e.workspace_id = ? AND e.user_id = ?',
            [$workspaceId, $userId],
        ) as $r) {
            $events[] = ['at' => (string) $r['at'], 'type' => 'learning', 'label' => 'Learning: ' . (string) $r['title'] . ' — ' . (string) $r['status'] . ' (' . (int) $r['progress_percent'] . '%)'];
        }

        // Manual mini-CRM entries — calls, messages, meetings, notes, updates.
        foreach ($this->connection->select(
            'SELECT c.occurred_at AS at, c.kind, c.body, u.name AS author FROM candidate_timeline_entries c
               LEFT JOIN users u ON u.id = c.created_by
              WHERE c.workspace_id = ? AND c.user_id = ? AND c.deleted_at IS NULL',
            [$workspaceId, $userId],
        ) as $r) {
            $events[] = [
                'at' => (string) $r['at'],
                'type' => (string) $r['kind'],
                'label' => (string) $r['body'],
                'by' => (string) ($r['author'] ?? 'system'),
            ];
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

        // Guarantee every event exposes an actor field (empty for observed events).
        $events = array_map(static fn (array $e): array => $e + ['by' => ''], $events);

        usort($events, static fn (array $a, array $b): int => strcmp((string) $b['at'], (string) $a['at']));

        return $events;
    }

    private function normalizeKind(string $kind): string
    {
        $kind = strtolower(trim($kind));

        return in_array($kind, ['update', 'call', 'message', 'meeting', 'note'], true) ? $kind : 'update';
    }
}
