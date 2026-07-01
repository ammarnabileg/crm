<?php

declare(strict_types=1);

namespace Nizam\Kernel\Application;

/**
 * Marker interface for a query: a request for data that does not change state.
 *
 * A query is an immutable message describing what the caller wants to read. It is dispatched
 * through a {@see QueryBus} to exactly one {@see QueryHandler}, which returns a read model / DTO.
 */
interface Query
{
}
