<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Exception;

/**
 * Raised when a plugin fails validation against the platform.
 *
 * Covers version and constraint parse failures and the checks the
 * {@see \Nizam\Platform\Plugin\Service\PluginValidator} performs — a version string that is not valid
 * SemVer, a version constraint that cannot be parsed, or an entry-point class that does not fulfil the
 * contract its declared kind requires. Carries error code `PLUGIN.VALIDATION_FAILED`.
 */
final class PluginValidationException extends PluginException
{
    /**
     * The stable error code for a validation failure.
     */
    public const string CODE = 'PLUGIN.VALIDATION_FAILED';

    /**
     * A version string was not a valid SemVer 2.0.0 version.
     */
    public static function invalidVersion(string $version): self
    {
        return new self(self::CODE, sprintf('"%s" is not a valid semantic version.', $version));
    }

    /**
     * A version-constraint expression could not be parsed.
     */
    public static function invalidConstraint(string $constraint): self
    {
        return new self(self::CODE, sprintf('"%s" is not a valid version constraint.', $constraint));
    }

    /**
     * The entry-point class does not implement the contract its declared kind requires.
     */
    public static function entryPointDoesNotImplementContract(string $entryPoint, string $contract): self
    {
        return new self(
            self::CODE,
            sprintf('Entry-point class "%s" must implement the kind contract "%s".', $entryPoint, $contract),
        );
    }

    /**
     * A validation check failed with a specific reason.
     */
    public static function forReason(string $reason): self
    {
        return new self(self::CODE, $reason);
    }
}
