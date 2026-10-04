<?php

declare(strict_types=1);

namespace HaHireAI\Core\Modules\Exceptions;

use RuntimeException;

/** Thrown for module registration/dependency errors (e.g. cycles). */
final class ModuleException extends RuntimeException
{
}
