<?php

declare(strict_types=1);

namespace Nizam\Kernel\Application;

use Nizam\Platform\Exception\PlatformException;

/**
 * An in-memory {@see QueryBus} with a one-to-one query-to-handler registry.
 *
 * Mirrors {@see SimpleCommandBus} for the read side: handlers are registered by query class name
 * and return a read model. Duplicate registration and dispatching an unregistered query both
 * throw, since either indicates a wiring bug.
 */
final class SimpleQueryBus implements QueryBus
{
    /**
     * Handlers keyed by query class name.
     *
     * @var array<class-string, callable>
     */
    private array $handlers = [];

    /**
     * Bind a handler to a query class.
     *
     * @param class-string $queryClass The query's fully-qualified class name.
     * @param callable     $handler    Receives the query instance and returns its read model.
     *
     * @throws PlatformException When a handler is already registered for the query.
     */
    public function register(string $queryClass, callable $handler): void
    {
        if (isset($this->handlers[$queryClass])) {
            throw new PlatformException(sprintf(
                'A query handler is already registered for "%s".',
                $queryClass,
            ));
        }

        $this->handlers[$queryClass] = $handler;
    }

    /**
     * Dispatch a query to its registered handler.
     *
     * @throws PlatformException When no handler is registered for the query's class.
     */
    public function dispatch(Query $query): mixed
    {
        $class = $query::class;

        if (!isset($this->handlers[$class])) {
            throw new PlatformException(sprintf('No query handler registered for "%s".', $class));
        }

        return ($this->handlers[$class])($query);
    }

    /**
     * Whether a handler is registered for the given query class.
     *
     * @param class-string $queryClass
     */
    public function hasHandlerFor(string $queryClass): bool
    {
        return isset($this->handlers[$queryClass]);
    }
}
