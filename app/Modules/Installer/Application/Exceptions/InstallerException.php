<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Installer\Application\Exceptions;

use RuntimeException;

/** Thrown for installer guard violations (e.g. re-running after lock). */
final class InstallerException extends RuntimeException
{
}
