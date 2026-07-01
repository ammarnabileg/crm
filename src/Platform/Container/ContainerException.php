<?php

declare(strict_types=1);

namespace Nizam\Platform\Container;

use Nizam\Platform\Exception\PlatformException;
use Psr\Container\ContainerExceptionInterface;

/**
 * Thrown when the container fails to resolve an entry for reasons other than "not found".
 *
 * Typical causes are an unresolvable constructor dependency, an un-typed or built-in scalar
 * parameter with no default, or a circular dependency. Implements the PSR-11
 * {@see ContainerExceptionInterface} so consumers can catch container failures generically.
 */
final class ContainerException extends PlatformException implements ContainerExceptionInterface
{
}
