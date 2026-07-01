<?php

declare(strict_types=1);

namespace Nizam\Platform\Exception;

use InvalidArgumentException as BaseInvalidArgumentException;

/**
 * Thrown when a caller passes an argument that violates a method's precondition.
 *
 * Extends the SPL {@see \InvalidArgumentException} so generic SPL handlers keep working while
 * still giving the platform a namespaced type it can target specifically (for example, in the
 * guard helpers of {@see \Nizam\Platform\Support\Assert}).
 */
class InvalidArgumentException extends BaseInvalidArgumentException
{
}
