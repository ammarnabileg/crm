<?php

declare(strict_types=1);

namespace HaHireAI\Core\Routing\Exceptions;

use RuntimeException;

/** Thrown/!signalled when the path matches but the HTTP method does not (405). */
final class MethodNotAllowedException extends RuntimeException
{
}
