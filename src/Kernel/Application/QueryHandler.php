<?php

declare(strict_types=1);

namespace Nizam\Kernel\Application;

/**
 * Handles exactly one type of {@see Query} and returns its read model.
 *
 * There is a one-to-one mapping between a query type and its handler, enforced by the
 * {@see QueryBus}. Handlers must not mutate state.
 */
interface QueryHandler
{
    /**
     * Answer the query.
     *
     * @return mixed The read model / DTO the query asked for.
     */
    public function handle(Query $query): mixed;
}
