<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Models\DecisionReview;
use InvalidArgumentException;

/**
 * Human-in-the-Loop oversight of Decision Engine output (docs/51 Human-in-the-Loop,
 * §20). A person reviews a `decision_records` row and takes one of four actions —
 * approve, edit, reject, request_changes — every one of which is logged as an
 * immutable `decision_reviews` row WITH a reason (the why is always captured), and
 * stamped back onto the decision (review_status / reviewed_at / reviewed_by) so the
 * latest verdict travels with the decision.
 *
 * For an `edit`, the reviewer's allowed field changes (normalized_score, passed,
 * recommendation_id, summary) are applied to the decision as the human override of
 * the automated result. All writes are tenant-scoped: the decision row is matched on
 * both id AND the active workspace_id, so a review can never touch another tenant's
 * decision.
 */
final class HumanReview
{
    /** The only oversight actions a reviewer may take (config-driven, no ENUM). */
    public const ACTIONS = ['approve', 'edit', 'reject', 'request_changes'];

    /** Decision fields a reviewer is allowed to override on an `edit`. */
    private const EDITABLE_FIELDS = ['normalized_score', 'passed', 'recommendation_id', 'summary'];

    /**
     * Record a human review of a decision and stamp the verdict back onto it.
     *
     * @param array<string, mixed> $changes Field overrides applied when $action is
     *                                       'edit' (intersected with EDITABLE_FIELDS).
     *
     * @throws InvalidArgumentException When $action is not one of self::ACTIONS.
     */
    public function review(
        int $decisionId,
        string $action,
        ?int $reviewerId = null,
        ?string $reason = null,
        array $changes = []
    ): DecisionReview {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException("Unsupported review action [{$action}].");
        }

        $db = app('db');
        $workspaceId = tenant()->id();

        // Only the changes the reviewer is allowed to apply (for an edit).
        $applied = $action === 'edit'
            ? array_intersect_key($changes, array_flip(self::EDITABLE_FIELDS))
            : [];

        // 1) Append the immutable review log row (changes JSON-encoded on write —
        //    Model casts are read-only, so arrays must be encoded by the caller).
        $review = DecisionReview::create([
            'decision_id' => $decisionId,
            'reviewer_id' => $reviewerId,
            'action'      => $action,
            'reason'      => $reason,
            'changes'     => $applied !== []
                ? json_encode($applied, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : null,
            'created_at'  => now(),
        ]);

        // 2) Stamp the verdict (and any edits) back onto the decision row, scoped to
        //    the active tenant so it can never cross a workspace boundary.
        $update = [
            'review_status' => $action,
            'reviewed_at'   => now(),
            'reviewed_by'   => $reviewerId,
            'updated_at'    => now(),
        ];

        if ($applied !== []) {
            if (array_key_exists('normalized_score', $applied)) {
                $update['normalized_score'] = (float) $applied['normalized_score'];
            }
            if (array_key_exists('passed', $applied)) {
                $update['passed'] = $applied['passed'] ? 1 : 0;
            }
            if (array_key_exists('recommendation_id', $applied)) {
                $update['recommendation_id'] = $applied['recommendation_id'] !== null
                    ? (int) $applied['recommendation_id']
                    : null;
            }
            if (array_key_exists('summary', $applied)) {
                $update['summary'] = (string) $applied['summary'];
            }
        }

        $db->table('decision_records')
            ->where('id', '=', $decisionId)
            ->where('workspace_id', '=', $workspaceId)
            ->update($update);

        return $review;
    }

    /**
     * The ordered review history for a decision (oldest first), tenant-scoped.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(int $decisionId): array
    {
        return app('db')->table('decision_reviews')
            ->where('decision_id', '=', $decisionId)
            ->where('workspace_id', '=', tenant()->id())
            ->orderBy('id')
            ->get();
    }
}
