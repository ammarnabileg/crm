<?php

declare(strict_types=1);

namespace Nizam\Platform\Exception;

use RuntimeException;

/**
 * Base class for every unexpected/runtime failure raised by the platform.
 *
 * Exceptions in this hierarchy represent *programmer* or *infrastructure* errors that are not
 * part of a use case's normal control flow. Expected/business failures are modelled with
 * {@see \Nizam\Platform\Support\Result} instead. Catching this base type lets an outer boundary
 * (HTTP kernel, console kernel, queue worker) convert any platform failure into a safe,
 * user-facing response without leaking stack traces.
 */
class PlatformException extends RuntimeException
{
}
