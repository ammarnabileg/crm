<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Workflow\Domain\Exceptions;

use RuntimeException;

/** Thrown when a Formula-node expression is invalid; surfaced as a step error. */
final class FormulaException extends RuntimeException
{
}
