<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Enum;

/**
 * How a role routes work for approval.
 *
 * A behavior style trait. String-backed for stable persistence in trait columns and read models.
 */
enum ApprovalStyle: string
{
    /** Approvers act one after another in a fixed order. */
    case StrictSequential = 'strict_sequential';

    /** Approvers review concurrently and any/all must sign off. */
    case ParallelReview = 'parallel_review';

    /** A single designated approver is sufficient. */
    case SingleApprover = 'single_approver';

    /** Approval routing depends on a value or risk threshold. */
    case ThresholdBased = 'threshold_based';
}
