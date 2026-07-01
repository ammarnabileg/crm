<?php

declare(strict_types=1);

namespace Nizam\Kernel\Application;

/**
 * Routes a {@see Command} to its single registered handler.
 *
 * The bus decouples callers from handlers: a caller dispatches a command object and the bus finds
 * and invokes the one handler bound to that command type. Cross-cutting concerns (transactions,
 * logging, validation) can be layered as decorators around an implementation.
 */
interface CommandBus
{
    /**
     * Dispatch a command to its handler.
     *
     * @return mixed The handler's optional result.
     */
    public function dispatch(Command $command): mixed;
}
