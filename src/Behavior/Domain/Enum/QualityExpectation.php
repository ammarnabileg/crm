<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\Enum;

/**
 * The quality bar a role holds work to.
 *
 * A behavior style trait. String-backed for stable persistence in trait columns and read models.
 */
enum QualityExpectation: string
{
    /** Meets the baseline acceptable quality. */
    case Baseline = 'baseline';

    /** Holds work to a high quality bar. */
    case High = 'high';

    /** Targets zero defects. */
    case ZeroDefect = 'zero_defect';
}
