<?php

declare(strict_types=1);

namespace HaHireAI\Core\Routing\Exceptions;

use RuntimeException;

/** Thrown when no route matches (HTTP 404). */
final class RouteNotFoundException extends RuntimeException
{
}
