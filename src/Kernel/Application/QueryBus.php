<?php

declare(strict_types=1);

namespace Nizam\Kernel\Application;

/**
 * Routes a {@see Query} to its single registered handler and returns the read model.
 *
 * @see CommandBus for the write-side counterpart.
 */
interface QueryBus
{
    /**
     * Dispatch a query to its handler.
     *
     * @return mixed The handler's read model / DTO.
     */
    public function dispatch(Query $query): mixed;
}
