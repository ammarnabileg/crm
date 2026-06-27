<?php

declare(strict_types=1);

namespace App\Services\Interview;

use App\Core\Database;
use App\Core\Model;
use RuntimeException;

/**
 * The Interview State Machine (docs/51 §5, AI Interview Engine P1).
 *
 * Drives an interview through configuration-driven stages (`interview_states`)
 * instead of a message chain. It enforces legal transitions, records an
 * append-only audit (`interview_state_transitions`), and updates
 * `interviews.state_id`. Because stages are data, pause/resume/recover are just
 * transitions — making interviews easy to stop, continue and recover.
 *
 * Transition rules (no illegal jumps):
 *  - start: a null current stage may only move to the `is_initial` stage;
 *  - a terminal stage cannot transition out;
 *  - PAUSE: any non-terminal stage may move to `waiting`;
 *  - RESUME: from `waiting` to any active non-terminal stage;
 *  - ADVANCE: to the next stage by `sort_order` (+1);
 *  - configurable jumps via a stage's `meta.allowed_next` keys.
 */
final class StateMachine
{
    private const PAUSE = 'waiting';

    /** @var array<string, array<string, mixed>> key => state row */
    private array $states = [];

    public function __construct(private readonly Database $db)
    {
        $this->loadStates();
    }

    /** Resolve via the container's connection by default. */
    public static function make(): self
    {
        return new self(app('db'));
    }

    /** @return array<string, array<string,mixed>> */
    public function states(): array
    {
        return $this->states;
    }

    public function initialStateKey(): ?string
    {
        foreach ($this->states as $key => $row) {
            if ((int) $row['is_initial'] === 1) {
                return $key;
            }
        }

        return null;
    }

    public function isTerminal(string $key): bool
    {
        return isset($this->states[$key]) && (int) $this->states[$key]['is_terminal'] === 1;
    }

    /**
     * Whether a transition from $from (null = not started) to $to is legal.
     */
    public function canTransition(?string $from, string $to): bool
    {
        $toRow = $this->states[$to] ?? null;
        if ($toRow === null || (int) $toRow['is_active'] !== 1) {
            return false;
        }

        if ($from === null) {
            return $to === $this->initialStateKey();
        }

        $fromRow = $this->states[$from] ?? null;
        if ($fromRow === null || $from === $to) {
            return false;
        }
        if ((int) $fromRow['is_terminal'] === 1) {
            return false;
        }

        // Pause / resume.
        if ($to === self::PAUSE) {
            return true;
        }
        if ($from === self::PAUSE) {
            return true;
        }

        // Linear advance.
        if ((int) $toRow['sort_order'] === (int) $fromRow['sort_order'] + 1) {
            return true;
        }

        // Configurable jumps.
        $allowed = $this->allowedNext($fromRow);

        return in_array($to, $allowed, true);
    }

    /**
     * Perform a transition: validate, update interviews.state_id, append the audit
     * row. Returns the new state id. Throws on an illegal/unknown transition.
     */
    public function transition(int $interviewId, string $toKey, ?int $actorId = null, ?string $reason = null): int
    {
        $interview = $this->db->table('interviews')->where('id', '=', $interviewId)->first();
        if ($interview === null) {
            throw new RuntimeException("Interview {$interviewId} not found.");
        }

        $workspaceId = (int) $interview['workspace_id'];
        $fromStateId = $interview['state_id'] !== null ? (int) $interview['state_id'] : null;
        $fromKey = $fromStateId !== null ? $this->keyForId($fromStateId) : null;

        if (! $this->canTransition($fromKey, $toKey)) {
            throw new RuntimeException(
                "Illegal interview transition: '" . ($fromKey ?? 'none') . "' → '{$toKey}'."
            );
        }

        $toStateId = (int) ($this->states[$toKey]['id']);
        $now = now();

        $this->db->table('interviews')->where('id', '=', $interviewId)
            ->update(['state_id' => $toStateId, 'updated_at' => $now]);

        $this->db->table('interview_state_transitions')->insert([
            'uuid'          => Model::generateUuid(),
            'workspace_id'  => $workspaceId,
            'interview_id'  => $interviewId,
            'from_state_id' => $fromStateId,
            'to_state_id'   => $toStateId,
            'changed_by'    => $actorId,
            'reason'        => $reason,
            'created_at'    => $now,
        ]);

        return $toStateId;
    }

    /** Move a not-yet-started interview into its initial stage. */
    public function start(int $interviewId, ?int $actorId = null): int
    {
        $initial = $this->initialStateKey();
        if ($initial === null) {
            throw new RuntimeException('No initial interview state is configured.');
        }

        return $this->transition($interviewId, $initial, $actorId, 'Interview started');
    }

    public function pause(int $interviewId, ?int $actorId = null, ?string $reason = null): int
    {
        return $this->transition($interviewId, self::PAUSE, $actorId, $reason ?? 'Paused');
    }

    public function resume(int $interviewId, string $toKey, ?int $actorId = null): int
    {
        return $this->transition($interviewId, $toKey, $actorId, 'Resumed');
    }

    /**
     * @param array<string,mixed> $stateRow
     * @return string[]
     */
    private function allowedNext(array $stateRow): array
    {
        $meta = $stateRow['meta'] ?? null;
        if (is_string($meta)) {
            $meta = json_decode($meta, true);
        }

        return is_array($meta) && isset($meta['allowed_next']) && is_array($meta['allowed_next'])
            ? array_values(array_filter($meta['allowed_next'], 'is_string'))
            : [];
    }

    private function keyForId(int $id): ?string
    {
        foreach ($this->states as $key => $row) {
            if ((int) $row['id'] === $id) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Load the active stage catalog: system rows (workspace_id IS NULL) ordered by
     * sort_order. Tenant overrides (non-null workspace_id, same key) take
     * precedence when present.
     */
    private function loadStates(): void
    {
        $rows = $this->db->table('interview_states')
            ->where('is_active', '=', 1)
            ->orderBy('sort_order')
            ->get();

        $system = [];
        $tenant = [];
        foreach ($rows as $row) {
            if ($row['workspace_id'] === null) {
                $system[$row['key']] = $row;
            } else {
                $tenant[$row['key']] = $row;
            }
        }

        // Tenant rows override system rows of the same key.
        $this->states = array_merge($system, $tenant);
    }
}
