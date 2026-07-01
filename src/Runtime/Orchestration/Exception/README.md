# Runtime\Orchestration\Exception

**Purpose.** The typed failures the orchestration layer raises when a request cannot be routed,
authorized, or coordinated — the failures that occur *before or around* an execution, as distinct from the
Execution layer's `ExecutionApplicationException` (which covers driving an execution) and domain invariant
violations (`ExecutionException`).

**Responsibilities.**
- `OrchestrationException` — one final class with named constructors and stable, dotted
  `RUNTIME.ORCHESTRATION.*` codes: `INVALID_REQUEST`, `TENANT_UNAVAILABLE`, `USER_UNAVAILABLE`,
  `DEPARTMENT_UNRESOLVED`, `MANAGER_PLUGIN_MISSING`, `WORKER_PLUGIN_MISSING`, `COLLABORATION_NOT_PERMITTED`,
  and `PERMISSION_DENIED`. Callers branch on `errorCode()`, never on message text.

**Dependencies.** PHP's SPL `\RuntimeException` only — deliberately, so the orchestration layer's error
type stays coupled to nothing beyond PHP. (Expected, business-level failures elsewhere in the platform use
`Nizam\Platform\Support\Result`; these are the unroutable/unauthorized cases where an exception is the
right control-flow.)

**Public interfaces.** `OrchestrationException` and its named constructors.
