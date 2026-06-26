<?php

declare(strict_types=1);

namespace Tests;

use RuntimeException;

/**
 * Thrown by TestCase assertions on failure. Distinct from ordinary errors so
 * the runner can report "failed assertion" vs "errored" separately.
 */
final class AssertionFailed extends RuntimeException
{
}
