# Runtime\Execution\Application\Exception

**Purpose.** The typed orchestration-level failures the Execution application layer raises — as
opposed to domain invariant violations, which surface from `Runtime\Execution\Domain\Exception` as
`ExecutionException` subtypes. These signal that a *use case* could not be carried out, not that a
business rule was broken.

**Responsibilities.**
- `ExecutionApplicationException` — a single typed exception with named constructors and stable,
  dotted `EXEC.APPLICATION.*` error codes so callers branch on the failure kind without matching
  messages:
  - `lockUnavailable` — the execution is already being processed; its lock could not be acquired.
  - `executionNotFound` — no execution with the referenced id exists.
  - `noEventsToReplay` — the event store holds nothing for the execution, so it cannot be rebuilt.
  - `notRecoverable` — recovery was requested for an execution that is not in the `Failed` state.
  - `pipelineStalled` — a stage produced no actionable outcome (invalid request, empty plan, worker
    errors), halting the run.

**Dependencies.** PHP's SPL `\RuntimeException` only — the application layer stays coupled to PHP, the
Kernel, and its own domain, honoring the hexagonal boundary.

**Public interfaces.** `ExecutionApplicationException` (+ its named constructors and `errorCode()`).

**Key rules honored.** Expected, recoverable-by-caller failures are exceptions with stable codes here;
genuinely *expected* validation outcomes use `Result` (see `ExecutionValidator`); domain invariants
throw from the domain layer, never here.
