<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Enum;

/**
 * How a role delegates work to others.
 *
 * A behavior style trait. String-backed for stable persistence in trait columns and read models.
 */
enum DelegationStrategy: string
{
    /** Retains work rather than delegating. */
    case Retain = 'retain';

    /** Delegates routine work while retaining judgment calls. */
    case DelegateRoutine = 'delegate_routine';

    /** Delegates broadly but reviews the outcome. */
    case DelegateWithReview = 'delegate_with_review';

    /** Delegates fully, including the review. */
    case FullDelegation = 'full_delegation';
}
