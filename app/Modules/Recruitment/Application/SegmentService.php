<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application;

use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Recruitment\Domain\TalentSegment\SegmentCompiler;
use HaHireAI\Modules\Recruitment\Domain\TalentSegment\SegmentField;
use HaHireAI\Modules\Recruitment\Domain\TalentSegment\SegmentRule;
use HaHireAI\Shared\Ulid;

/**
 * Smart Segments — named, saved candidate filters for the Talent Pool (a smarter
 * alternative to one-shot search). CRUD over segments + their rules, and a
 * prepared, workspace-scoped {@see self::evaluate()} that runs the compiled rules
 * over the workspace's candidates and returns each match with the reasons it
 * matched. Every query is filtered by workspace_id (tenant isolation is absolute).
 */
final class SegmentService
{
    private const MATCH_TYPES = ['all', 'any'];

    public function __construct(
        private readonly Connection $connection,
        private readonly SegmentCompiler $compiler = new SegmentCompiler(),
    ) {
    }

    public function createSegment(string $workspaceId, string $name, string $matchType = 'all', ?string $createdBy = null): string
    {
        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO talent_segments (id, workspace_id, name, match_type, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $name, $this->normalizeMatchType($matchType), $createdBy, $now, $now],
        );

        return $id;
    }

    public function updateSegment(string $workspaceId, string $segmentId, string $name, string $matchType): void
    {
        $this->connection->statement(
            'UPDATE talent_segments SET name = ?, match_type = ?, updated_at = ? WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
            [$name, $this->normalizeMatchType($matchType), gmdate('Y-m-d H:i:s'), $segmentId, $workspaceId],
        );
    }

    public function deleteSegment(string $workspaceId, string $segmentId): void
    {
        $this->connection->statement(
            'UPDATE talent_segments SET deleted_at = ? WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
            [gmdate('Y-m-d H:i:s'), $segmentId, $workspaceId],
        );
    }

    /** @return list<array<string, mixed>> segments with their rule counts */
    public function listSegments(string $workspaceId): array
    {
        return $this->connection->select(
            'SELECT s.id, s.name, s.match_type, s.created_at,
                    (SELECT COUNT(*) FROM talent_segment_rules r WHERE r.segment_id = s.id) AS rules
               FROM talent_segments s
              WHERE s.workspace_id = ? AND s.deleted_at IS NULL
              ORDER BY s.created_at DESC',
            [$workspaceId],
        );
    }

    /** @return array<string, mixed>|null */
    public function findSegment(string $workspaceId, string $segmentId): ?array
    {
        return $this->connection->selectOne(
            'SELECT * FROM talent_segments WHERE id = ? AND workspace_id = ? AND deleted_at IS NULL',
            [$segmentId, $workspaceId],
        );
    }

    /**
     * Replace all rules of a segment in one shot (the builder saves the whole set).
     * Invalid rows are skipped rather than aborting the save.
     *
     * @param  list<array{field: string, value?: string}>  $rules
     */
    public function replaceRules(string $workspaceId, string $segmentId, array $rules): void
    {
        if ($this->findSegment($workspaceId, $segmentId) === null) {
            return;
        }

        $this->connection->statement(
            'DELETE FROM talent_segment_rules WHERE workspace_id = ? AND segment_id = ?',
            [$workspaceId, $segmentId],
        );

        $now = gmdate('Y-m-d H:i:s');
        $position = 0;
        foreach ($rules as $raw) {
            $field = (string) ($raw['field'] ?? '');
            $value = trim((string) ($raw['value'] ?? ''));
            if (! SegmentField::isValid($field)) {
                continue;
            }
            if (SegmentField::requiresValue($field) && $value === '') {
                continue;
            }
            $this->connection->statement(
                'INSERT INTO talent_segment_rules (id, workspace_id, segment_id, field, operator, value, position, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [Ulid::generate(), $workspaceId, $segmentId, $field, SegmentField::operator($field), $value, $position++, $now],
            );
        }
    }

    /** @return list<array<string, mixed>> the raw rule rows for a segment (builder + display) */
    public function rules(string $workspaceId, string $segmentId): array
    {
        return $this->connection->select(
            'SELECT id, field, operator, value, position FROM talent_segment_rules
              WHERE workspace_id = ? AND segment_id = ? ORDER BY position, created_at',
            [$workspaceId, $segmentId],
        );
    }

    /**
     * Run a segment: compile its rules to correlated predicates and select every
     * workspace candidate that satisfies them (AND/OR by match_type). Each row is
     * returned with the reasons it matched. A segment with no rules matches nobody.
     *
     * @return list<array{user_id: string, name: string, email: string, reasons: list<string>, reason: string}>
     */
    public function evaluate(string $workspaceId, string $segmentId): array
    {
        $segment = $this->findSegment($workspaceId, $segmentId);
        if ($segment === null) {
            return [];
        }

        $ruleObjects = [];
        foreach ($this->rules($workspaceId, $segmentId) as $row) {
            try {
                $ruleObjects[] = SegmentRule::fromRow($row);
            } catch (\InvalidArgumentException) {
                // A rule that no longer validates (e.g. catalog change) is ignored.
            }
        }
        if ($ruleObjects === []) {
            return [];
        }

        $compiled = $this->compiler->compile($ruleObjects, (string) $segment['match_type'], gmdate('Y-m-d H:i:s'));
        $predicates = $compiled['predicates'];

        // SELECT list: one boolean column per predicate to explain each match…
        $selectExprs = [];
        $selectBindings = [];
        $labels = [];
        foreach ($predicates as $i => $p) {
            $selectExprs[] = '(' . $p['sql'] . ') AS r' . $i;
            foreach ($p['bindings'] as $b) {
                $selectBindings[] = $b;
            }
            $labels[$i] = $p['label'];
        }

        // …and the same predicates in WHERE, joined by the match glue, to filter.
        $whereExprs = [];
        $whereBindings = [];
        foreach ($predicates as $p) {
            $whereExprs[] = '(' . $p['sql'] . ')';
            foreach ($p['bindings'] as $b) {
                $whereBindings[] = $b;
            }
        }

        $sql = 'SELECT cp.user_id, u.name, u.email, ' . implode(', ', $selectExprs)
            . ' FROM candidate_profiles cp JOIN users u ON u.id = cp.user_id'
            . ' WHERE cp.workspace_id = ?'
            . ' AND (' . implode($compiled['glue'], $whereExprs) . ')'
            . ' ORDER BY u.name';

        // Bindings in exact textual order: SELECT predicates, workspace_id, WHERE predicates.
        $bindings = [...$selectBindings, $workspaceId, ...$whereBindings];

        $rows = $this->connection->select($sql, $bindings);

        $out = [];
        foreach ($rows as $row) {
            $reasons = [];
            foreach ($labels as $i => $label) {
                if ((int) ($row['r' . $i] ?? 0) === 1) {
                    $reasons[] = $label;
                }
            }
            $out[] = [
                'user_id' => (string) $row['user_id'],
                'name' => (string) $row['name'],
                'email' => (string) $row['email'],
                'reasons' => $reasons,
                'reason' => implode(' · ', $reasons),
            ];
        }

        return $out;
    }

    private function normalizeMatchType(string $matchType): string
    {
        return in_array($matchType, self::MATCH_TYPES, true) ? $matchType : 'all';
    }
}
