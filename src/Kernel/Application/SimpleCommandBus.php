<?php

declare(strict_types=1);

namespace Nizam\Kernel\Application;

use Nizam\Platform\Exception\PlatformException;

/**
 * An in-memory {@see CommandBus} with a one-to-one command-to-handler registry.
 *
 * Handlers are registered by command class name against a callable (an invokable handler object, a
 * closure, or any callable). Dispatching looks up the handler for the command's concrete class and
 * invokes it with the command. Registering a second handler for the same command, or dispatching a
 * command with no handler, is a programmer error and throws.
 */
final class SimpleCommandBus implements CommandBus
{
    /**
     * Handlers keyed by command class name.
     *
     * @var array<class-string, callable>
     */
    private array $handlers = [];

    /**
     * Bind a handler to a command class.
     *
     * @param class-string $commandClass The command's fully-qualified class name.
     * @param callable     $handler      Receives the command instance and returns its result.
     *
     * @throws PlatformException When a handler is already registered for the command.
     */
    public function register(string $commandClass, callable $handler): void
    {
        if (isset($this->handlers[$commandClass])) {
            throw new PlatformException(sprintf(
                'A command handler is already registered for "%s".',
                $commandClass,
            ));
        }

        $this->handlers[$commandClass] = $handler;
    }

    /**
     * Dispatch a command to its registered handler.
     *
     * @throws PlatformException When no handler is registered for the command's class.
     */
    public function dispatch(Command $command): mixed
    {
        $class = $command::class;

        if (!isset($this->handlers[$class])) {
            throw new PlatformException(sprintf('No command handler registered for "%s".', $class));
        }

        return ($this->handlers[$class])($command);
    }

    /**
     * Whether a handler is registered for the given command class.
     *
     * @param class-string $commandClass
     */
    public function hasHandlerFor(string $commandClass): bool
    {
        return isset($this->handlers[$commandClass]);
    }
}
