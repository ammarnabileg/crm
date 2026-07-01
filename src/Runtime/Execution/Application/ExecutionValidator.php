<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Application;

use Nizam\Platform\Support\Result;
use Nizam\Runtime\Execution\Application\ValueObject\ExecutionRequest;

/**
 * The application service that validates an {@see ExecutionRequest} before it is executed.
 *
 * Validation is an expected, business-level check — a malformed request is not a programmer error —
 * so this service returns a {@see Result} rather than throwing: {@see Result::ok()} carrying the
 * validated request, or {@see Result::err()} with a stable dotted code and a friendly message when a
 * rule fails. It checks the invariants the Runtime cares about before any state is created: the intent
 * is named, the payload is well-formed, the retry and timeout budgets are sane, and — when references
 * are supplied — the department and manager references are non-empty. It performs no I/O.
 */
final class ExecutionValidator
{
    /**
     * The stable prefix shared by every validation error code.
     */
    public const string CODE_PREFIX = 'EXEC.VALIDATION';

    /**
     * Validate an execution request, returning the request on success or an error result on failure.
     *
     * @param ExecutionRequest $request The request to validate.
     *
     * @return Result A success carrying the validated {@see ExecutionRequest}, or a failure result.
     */
    public function validate(ExecutionRequest $request): Result
    {
        if (trim($request->intentRef()) === '') {
            return Result::err(
                self::CODE_PREFIX . '.MISSING_INTENT',
                'This request does not say what it wants done. Please provide an intent.',
            );
        }

        foreach ($request->payload() as $key => $_value) {
            if (!is_string($key) || $key === '') {
                return Result::err(
                    self::CODE_PREFIX . '.MALFORMED_PAYLOAD',
                    'The request details are malformed. Every payload field must have a name.',
                );
            }
        }

        $departmentRef = $request->departmentRef();
        if ($departmentRef !== null && trim($departmentRef) === '') {
            return Result::err(
                self::CODE_PREFIX . '.MALFORMED_DEPARTMENT',
                'The department this request is routed to is blank. Please choose a department.',
            );
        }

        $managerRef = $request->managerRef();
        if ($managerRef !== null && trim($managerRef) === '') {
            return Result::err(
                self::CODE_PREFIX . '.MALFORMED_MANAGER',
                'The manager assigned to this request is blank. Please choose a manager.',
            );
        }

        if ($request->retryPolicy()->maxAttempts() < 1) {
            return Result::err(
                self::CODE_PREFIX . '.INVALID_RETRY_POLICY',
                'The retry settings are invalid: at least one attempt must be allowed.',
            );
        }

        $timeout = $request->timeoutPolicy();
        if ($timeout->wallClockMs() < 1 || $timeout->perStepMs() < 1) {
            return Result::err(
                self::CODE_PREFIX . '.INVALID_TIMEOUT_POLICY',
                'The time-limit settings are invalid: the budgets must be positive.',
            );
        }

        return Result::ok($request);
    }
}
