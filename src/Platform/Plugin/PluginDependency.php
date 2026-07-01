<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin;

use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Plugin\Exception\PluginManifestException;

/**
 * A declared dependency of one plugin on another, at a version range.
 *
 * A dependency names another plugin (by its manifest name) and the {@see VersionConstraint} the
 * dependant needs it to satisfy. A dependency may be `optional`: an optional dependency that is
 * missing or unsatisfiable is skipped rather than blocking installation, whereas a required one that
 * cannot be satisfied is a hard error reported by the dependency resolver. This is the atom the
 * resolver uses to compute install order, detect cycles, and reject missing or incompatible required
 * dependencies.
 */
final class PluginDependency implements ValueObject
{
    /**
     * The permitted plugin-name shape: kebab or dotted, lowercase alphanumerics.
     */
    private const string NAME_PATTERN = '/^[a-z0-9]+([._-][a-z0-9]+)*$/';

    /**
     * @param string            $pluginName The manifest name of the depended-upon plugin.
     * @param VersionConstraint $constraint The version range the dependency must satisfy.
     * @param bool              $optional   Whether an unsatisfiable dependency should be skipped rather than fail.
     *
     * @throws PluginManifestException When the plugin name is not a valid manifest name.
     */
    public function __construct(
        private readonly string $pluginName,
        private readonly VersionConstraint $constraint,
        private readonly bool $optional = false,
    ) {
        $normalized = trim($pluginName);
        if ($normalized === '' || preg_match(self::NAME_PATTERN, $normalized) !== 1) {
            throw PluginManifestException::invalidDependencyName($pluginName);
        }
    }

    /**
     * The manifest name of the depended-upon plugin.
     */
    public function pluginName(): string
    {
        return $this->pluginName;
    }

    /**
     * The version range the dependency must satisfy.
     */
    public function constraint(): VersionConstraint
    {
        return $this->constraint;
    }

    /**
     * Whether the dependency is optional.
     */
    public function isOptional(): bool
    {
        return $this->optional;
    }

    /**
     * Whether the given version of the depended-upon plugin satisfies this dependency's constraint.
     */
    public function isSatisfiedBy(SemanticVersion $version): bool
    {
        return $this->constraint->satisfies($version);
    }

    /**
     * Value equality by name, constraint and optionality.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $this->pluginName === $other->pluginName
            && $this->constraint->equals($other->constraint)
            && $this->optional === $other->optional;
    }
}
