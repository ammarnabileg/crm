<?php

declare(strict_types=1);

namespace Nizam\Kernel\Application;

/**
 * Marker interface for a command: an intent to change state.
 *
 * A command is an immutable message describing something the caller wants done (e.g.
 * "RegisterUser"). It is dispatched through a {@see CommandBus} to exactly one
 * {@see CommandHandler}. Commands express intent, not results; any return value is incidental.
 */
interface Command
{
}
