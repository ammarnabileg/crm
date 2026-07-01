<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Exception;

/**
 * Raised when a plugin's dependencies cannot be satisfied.
 *
 * The {@see \Nizam\Platform\Plugin\Service\PluginDependencyResolver} raises this when it detects a
 * dependency cycle, a missing required dependency, or a required dependency whose available version
 * does not satisfy the declared constraint. Optional dependencies never trigger it. Carries error code
 * `PLUGIN.DEPENDENCY_UNRESOLVED`.
 */
final class PluginDependencyException extends PluginException
{
    /**
     * The stable error code for an unresolved dependency graph.
     */
    public const string CODE = 'PLUGIN.DEPENDENCY_UNRESOLVED';

    /**
     * A required dependency is not present among the available plugins.
     */
    public static function missingRequired(string $dependant, string $dependency): self
    {
        return new self(
            self::CODE,
            sprintf('Plugin "%s" requires "%s", which is not available.', $dependant, $dependency),
        );
    }

    /**
     * A required dependency is present but no available version satisfies the constraint.
     */
    public static function incompatibleRequired(string $dependant, string $dependency, string $constraint): self
    {
        return new self(
            self::CODE,
            sprintf(
                'Plugin "%s" requires "%s" %s, but no available version satisfies it.',
                $dependant,
                $dependency,
                $constraint,
            ),
        );
    }

    /**
     * The dependency graph contains a cycle through the given plugins.
     *
     * @param list<string> $cycle The plugin names forming the cycle, in order.
     */
    public static function cycleDetected(array $cycle): self
    {
        return new self(
            self::CODE,
            sprintf('Plugin dependency cycle detected: %s.', implode(' -> ', $cycle)),
        );
    }
}
