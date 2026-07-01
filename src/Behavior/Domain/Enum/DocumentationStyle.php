<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Enum;

/**
 * How thoroughly a role documents work.
 *
 * A behavior style trait. String-backed for stable persistence in trait columns and read models.
 */
enum DocumentationStyle: string
{
    /** Minimal documentation, only what is strictly required. */
    case Minimal = 'minimal';

    /** Standard documentation matching common expectations. */
    case Standard = 'standard';

    /** Comprehensive documentation covering rationale and detail. */
    case Comprehensive = 'comprehensive';
}
