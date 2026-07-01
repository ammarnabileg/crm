<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Exception;

/**
 * Raised when a plugin manifest has an invalid shape or invalid field.
 *
 * The {@see \Nizam\Platform\Plugin\PluginManifest} and the value objects it composes are
 * self-validating: a missing required field, a malformed name, an unknown kind, an invalid permission
 * key or dependency name all abort construction with this exception. Carries error code
 * `PLUGIN.MANIFEST_INVALID`.
 */
final class PluginManifestException extends PluginException
{
    /**
     * The stable error code for a manifest-shape violation.
     */
    public const string CODE = 'PLUGIN.MANIFEST_INVALID';

    /**
     * A required manifest field was absent or empty.
     */
    public static function missingField(string $field): self
    {
        return new self(self::CODE, sprintf('Plugin manifest is missing required field "%s".', $field));
    }

    /**
     * A manifest field held a value of the wrong type.
     */
    public static function invalidField(string $field, string $expected): self
    {
        return new self(self::CODE, sprintf('Plugin manifest field "%s" must be %s.', $field, $expected));
    }

    /**
     * The manifest name did not match the required kebab/dotted identifier shape.
     */
    public static function invalidName(string $name): self
    {
        return new self(
            self::CODE,
            sprintf('Plugin name "%s" must be a lowercase kebab or dotted identifier.', $name),
        );
    }

    /**
     * A declared kind value was not one of the known {@see \Nizam\Platform\Plugin\PluginKind} cases.
     */
    public static function unknownKind(string $kind): self
    {
        return new self(self::CODE, sprintf('Plugin manifest declares unknown kind "%s".', $kind));
    }

    /**
     * A permission key was empty or not a canonical dotted capability key.
     */
    public static function invalidPermissionKey(string $key): self
    {
        return new self(
            self::CODE,
            sprintf('Permission key "%s" must be a dotted, lowercase capability key.', $key),
        );
    }

    /**
     * A dependency named a plugin using an invalid manifest name.
     */
    public static function invalidDependencyName(string $name): self
    {
        return new self(
            self::CODE,
            sprintf('Dependency plugin name "%s" is not a valid plugin name.', $name),
        );
    }
}
