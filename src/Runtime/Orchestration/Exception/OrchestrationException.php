<?php

declare(strict_types=1);

namespace Nizam\Runtime\Orchestration\Exception;

use RuntimeException;

/**
 * Raised by the Orchestration layer when a request cannot be routed or coordinated.
 *
 * These are orchestration-level failures that occur *before or around* an execution: the request failed
 * validation, its tenant or user could not be resolved (or is inactive), no department/manager could be
 * routed to, a required Manager or Worker plugin is missing, or a worker attempted an illegal action
 * (talking to another worker directly, or requesting more workers outside a collaborative dispatch).
 * Each instance carries a stable, dotted code under {@see self::CODE_PREFIX} so callers can branch on
 * the failure kind without matching on messages. It extends the SPL {@see \RuntimeException} to keep the
 * orchestration layer coupled only to PHP, the Kernel, the Execution layer, and the Plugin contracts.
 */
final class OrchestrationException extends RuntimeException
{
    /**
     * The stable prefix shared by every Orchestration error code.
     */
    public const string CODE_PREFIX = 'RUNTIME.ORCHESTRATION';

    /**
     * @param string $errorCode The stable, dotted error code.
     * @param string $message   A human-readable description of the failure.
     */
    private function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * The request failed validation and cannot be routed.
     */
    public static function invalidRequest(string $reason): self
    {
        return new self(self::CODE_PREFIX . '.INVALID_REQUEST', $reason);
    }

    /**
     * The request's tenant could not be resolved or is inactive.
     */
    public static function tenantUnavailable(string $tenantId): self
    {
        return new self(
            self::CODE_PREFIX . '.TENANT_UNAVAILABLE',
            sprintf('The tenant "%s" could not be resolved or is not active.', $tenantId),
        );
    }

    /**
     * The request's acting user could not be resolved or is inactive.
     */
    public static function userUnavailable(string $userId): self
    {
        return new self(
            self::CODE_PREFIX . '.USER_UNAVAILABLE',
            sprintf('The user "%s" could not be resolved or is not active.', $userId),
        );
    }

    /**
     * No department/manager could be routed to for the request's intent.
     */
    public static function departmentUnresolved(string $intentRef): self
    {
        return new self(
            self::CODE_PREFIX . '.DEPARTMENT_UNRESOLVED',
            sprintf('No department or manager could be resolved for intent "%s".', $intentRef),
        );
    }

    /**
     * A required Manager plugin could not be loaded.
     */
    public static function managerPluginMissing(string $managerRef): self
    {
        return new self(
            self::CODE_PREFIX . '.MANAGER_PLUGIN_MISSING',
            sprintf('The manager plugin "%s" is not available.', $managerRef),
        );
    }

    /**
     * A required Worker plugin could not be loaded.
     */
    public static function workerPluginMissing(string $workerRef): self
    {
        return new self(
            self::CODE_PREFIX . '.WORKER_PLUGIN_MISSING',
            sprintf('The worker plugin "%s" is not available.', $workerRef),
        );
    }

    /**
     * A worker attempted to request more workers outside a collaborative dispatch.
     */
    public static function collaborationNotPermitted(): self
    {
        return new self(
            self::CODE_PREFIX . '.COLLABORATION_NOT_PERMITTED',
            'Additional workers may only be requested during a collaborative dispatch, by re-entering the coordinator.',
        );
    }

    /**
     * The granted permissions do not cover what a worker requires.
     */
    public static function permissionDenied(string $workerRef): self
    {
        return new self(
            self::CODE_PREFIX . '.PERMISSION_DENIED',
            sprintf('The granted permissions do not authorise dispatching worker "%s".', $workerRef),
        );
    }

    /**
     * The stable, dotted error code identifying the kind of failure.
     */
    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
