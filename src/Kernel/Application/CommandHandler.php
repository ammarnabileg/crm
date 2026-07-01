<?php

declare(strict_types=1);

namespace Nizam\Kernel\Application;

/**
 * Handles exactly one type of {@see Command}.
 *
 * A handler contains the application logic for its command: it orchestrates domain objects and
 * ports to carry out the intended state change. There is a one-to-one mapping between a command
 * type and its handler, enforced by the {@see CommandBus}.
 */
interface CommandHandler
{
    /**
     * Execute the command.
     *
     * @return mixed An optional result (e.g. the id of a created aggregate); often null.
     */
    public function handle(Command $command): mixed;
}
