<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Service;

use Nizam\Platform\Plugin\Exception\PluginValidationException;
use Nizam\Platform\Plugin\Port\HealthCheck;
use Nizam\Platform\Plugin\PluginManifest;
use Nizam\Platform\Plugin\PluginPermission;
use Nizam\Platform\Plugin\SemanticVersion;
use Nizam\Platform\Support\Result;
use ReflectionClass;
use Throwable;

/**
 * Validates a plugin manifest against the platform before it may be installed.
 *
 * The validator is the gate every plugin passes through at install time. It confirms the manifest is
 * structurally sound (the manifest value object already rejects malformed shapes, so here the checks
 * are semantic): the entry-point class exists and implements the SDK contract its declared
 * {@see \Nizam\Platform\Plugin\PluginKind} demands (verified by reflection, not by loading the plugin);
 * the current platform version satisfies the manifest's platform constraint; the declared permissions
 * and capabilities are well-formed and non-conflicting; the config schema is a well-shaped declarative
 * object; and a declared health-check class, if any, implements the {@see HealthCheck} port. Rather
 * than throwing on a business-level failure, it returns a {@see Result}: {@see Result::ok()} carrying
 * the validated manifest, or {@see Result::err()} with the {@see PluginValidationException::CODE} error
 * code and a human-readable reason, so callers can surface the failure without exception handling.
 */
final class PluginValidator
{
    /**
     * Validate a manifest for installation on the given platform version.
     *
     * @param PluginManifest $manifest        The manifest to validate.
     * @param string         $platformVersion The current platform version, as a SemVer string.
     *
     * @return Result A success carrying the manifest, or a failure describing the first violation.
     */
    public function validate(PluginManifest $manifest, string $platformVersion): Result
    {
        $platform = $this->parsePlatformVersion($platformVersion);
        if ($platform === null) {
            return Result::err(
                PluginValidationException::CODE,
                sprintf('"%s" is not a valid platform version.', $platformVersion),
            );
        }

        $failure = $this->checkPlatformConstraint($manifest, $platform)
            ?? $this->checkEntryPointContract($manifest)
            ?? $this->checkHealthCheckClass($manifest)
            ?? $this->checkPermissions($manifest)
            ?? $this->checkCapabilities($manifest)
            ?? $this->checkConfigSchema($manifest);

        if ($failure !== null) {
            return Result::err(PluginValidationException::CODE, $failure);
        }

        return Result::ok($manifest);
    }

    /**
     * Parse the supplied platform version, returning null when it is not valid SemVer.
     */
    private function parsePlatformVersion(string $platformVersion): ?SemanticVersion
    {
        try {
            return SemanticVersion::parse($platformVersion);
        } catch (PluginValidationException) {
            return null;
        }
    }

    /**
     * Verify the platform version satisfies the manifest's platform constraint.
     *
     * @return string|null A failure reason, or null when the constraint is satisfied.
     */
    private function checkPlatformConstraint(PluginManifest $manifest, SemanticVersion $platform): ?string
    {
        if ($manifest->platformConstraint()->satisfies($platform)) {
            return null;
        }

        return sprintf(
            'Plugin "%s" requires platform %s, but the platform is %s.',
            $manifest->name(),
            $manifest->platformConstraint()->raw(),
            (string) $platform,
        );
    }

    /**
     * Verify the entry-point class exists and implements its kind's SDK contract.
     *
     * The check is purely reflective: the class is examined, never instantiated, so a plugin with a
     * failing constructor cannot affect validation.
     *
     * @return string|null A failure reason, or null when the contract is satisfied.
     */
    private function checkEntryPointContract(PluginManifest $manifest): ?string
    {
        $entryPoint = $manifest->entryPointClass();
        $contract = $manifest->kind()->contract();

        if (!class_exists($entryPoint)) {
            return sprintf('Entry-point class "%s" does not exist or cannot be autoloaded.', $entryPoint);
        }

        try {
            $reflection = new ReflectionClass($entryPoint);
        } catch (Throwable $exception) {
            return sprintf('Entry-point class "%s" could not be reflected: %s', $entryPoint, $exception->getMessage());
        }

        if ($reflection->isAbstract() || $reflection->isInterface()) {
            return sprintf('Entry-point class "%s" must be a concrete, instantiable class.', $entryPoint);
        }

        if (!$reflection->implementsInterface($contract)) {
            return PluginValidationException::entryPointDoesNotImplementContract($entryPoint, $contract)->getMessage();
        }

        return null;
    }

    /**
     * Verify a declared health-check class exists and implements the {@see HealthCheck} port.
     *
     * @return string|null A failure reason, or null when there is no health check or it is valid.
     */
    private function checkHealthCheckClass(PluginManifest $manifest): ?string
    {
        $healthCheck = $manifest->healthCheckClass();
        if ($healthCheck === null) {
            return null;
        }

        if (!class_exists($healthCheck)) {
            return sprintf('Health-check class "%s" does not exist or cannot be autoloaded.', $healthCheck);
        }

        try {
            $reflection = new ReflectionClass($healthCheck);
        } catch (Throwable $exception) {
            return sprintf('Health-check class "%s" could not be reflected: %s', $healthCheck, $exception->getMessage());
        }

        if ($reflection->isAbstract() || $reflection->isInterface()) {
            return sprintf('Health-check class "%s" must be a concrete, instantiable class.', $healthCheck);
        }

        if (!$reflection->implementsInterface(HealthCheck::class)) {
            return sprintf(
                'Health-check class "%s" must implement "%s".',
                $healthCheck,
                HealthCheck::class,
            );
        }

        return null;
    }

    /**
     * Verify the required permissions are well-formed and free of duplicate keys.
     *
     * @return string|null A failure reason, or null when the permissions are valid.
     */
    private function checkPermissions(PluginManifest $manifest): ?string
    {
        $seen = [];
        foreach ($manifest->requiredPermissions() as $permission) {
            if (!$permission instanceof PluginPermission) {
                return 'Every required permission must be a permission value object.';
            }
            if (isset($seen[$permission->key()])) {
                return sprintf('Permission "%s" is declared more than once.', $permission->key());
            }
            $seen[$permission->key()] = true;
        }

        return null;
    }

    /**
     * Verify the required capabilities are non-empty strings.
     *
     * @return string|null A failure reason, or null when the capabilities are valid.
     */
    private function checkCapabilities(PluginManifest $manifest): ?string
    {
        foreach ($manifest->requiredCapabilities() as $capability) {
            if (trim($capability) === '') {
                return 'A required capability must be a non-empty string.';
            }
        }

        return null;
    }

    /**
     * Verify the config schema is a well-shaped declarative object (string keys throughout the top level).
     *
     * @return string|null A failure reason, or null when the schema is well-shaped.
     */
    private function checkConfigSchema(PluginManifest $manifest): ?string
    {
        foreach (array_keys($manifest->configSchema()) as $key) {
            if (!is_string($key) || trim($key) === '') {
                return 'The config schema must be keyed by non-empty field names.';
            }
        }

        return null;
    }
}
