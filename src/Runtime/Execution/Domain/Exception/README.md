# Runtime\Execution\Domain\Exception

**Purpose.** The typed exception hierarchy for every business-rule violation the execution domain can
raise. The base extends the SPL `\RuntimeException` (not a platform type) so the domain stays coupled
only to PHP and the Kernel. Every instance carries a stable, dotted error code under the `EXEC.*`
namespace so callers, logs, and API responses can branch on the failure kind without string-matching.

**Responsibilities.**
- `ExecutionException` — the base, carrying `errorCode()` (prefix `EXEC`).
- `IllegalExecutionTransition` (`EXEC.ILLEGAL_TRANSITION`) — a state change the machine forbids.
- `RetryExhausted` (`EXEC.RETRY_EXHAUSTED`) — the retry budget is spent.
- `ExecutionTimedOut` (`EXEC.TIMED_OUT`) — a wall-clock or per-step budget was exceeded.
- `ExecutionLockException` (`EXEC.LOCK`) — a lock could not be acquired, or a non-owner acted on it.
- `UnknownStepException` (`EXEC.UNKNOWN_STEP`) — an operation referenced a step not in the execution.

**Dependencies.** PHP `\RuntimeException`; the `ExecutionState` enum for message context. No I/O.

**Public interfaces.** Each exception's named constructor(s) and `errorCode()`; the `CODE` constant on
each subtype.
