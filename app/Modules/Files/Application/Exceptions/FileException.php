<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Files\Application\Exceptions;

use RuntimeException;

/** A recoverable file-domain error (too large, disallowed type, write failure). */
final class FileException extends RuntimeException
{
}
