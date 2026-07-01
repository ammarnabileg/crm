<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Exception;

use Nizam\Platform\Plugin\PluginState;

/**
 * Raised when a plugin lifecycle transition is attempted from a state that forbids it.
 *
 * The {@see \Nizam\Platform\Plugin\RegisteredPlugin} state machine only permits certain transitions
 * (for example, only an installed plugin may be enabled, and an uninstalled plugin is terminal). Any
 * illegal transition aborts with this exception. Carries error code `PLUGIN.INVALID_STATE_TRANSITION`.
 */
final class PluginStateException extends PluginException
{
    /**
     * The stable error code for an illegal state transition.
     */
    public const string CODE = 'PLUGIN.INVALID_STATE_TRANSITION';

    /**
     * Build the exception describing the operation and the offending current state.
     *
     * @param string      $operation The lifecycle operation attempted (e.g. "enable").
     * @param PluginState $current   The state the plugin was actually in.
     */
    public static function forOperation(string $operation, PluginState $current): self
    {
        return new self(
            self::CODE,
            sprintf('Cannot %s a plugin in state "%s".', $operation, $current->value),
        );
    }
}
