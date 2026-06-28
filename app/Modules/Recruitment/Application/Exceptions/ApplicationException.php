<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Application\Exceptions;

use RuntimeException;

/** Thrown for invalid application operations (duplicate apply, bad stage, …). */
final class ApplicationException extends RuntimeException
{
}
