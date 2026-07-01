<?php

declare(strict_types=1);

namespace Nizam\Platform\Container;

use Nizam\Platform\Exception\PlatformException;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Thrown by {@see Container::get()} when no entry is registered for the requested id and the id
 * is not an instantiable class the container can autowire.
 *
 * Implements the PSR-11 {@see NotFoundExceptionInterface}.
 */
final class NotFoundException extends PlatformException implements NotFoundExceptionInterface
{
}
