# Behavior\Domain\Exception

**Purpose.** The typed exception hierarchy for Behavior business-rule violations. Every type extends
`BehaviorDomainException` (itself a plain `\RuntimeException`, so the domain stays coupled only to
PHP and the Kernel) and carries a stable, dotted error code under the `BEHAVIOR.*` namespace so
callers, logs, and API responses can branch on the failure kind without matching messages.

**Responsibilities.**
- `BehaviorDomainException` — the base type; exposes `errorCode()` and the `BEHAVIOR` `CODE_PREFIX`.
- `UnknownRevisionException` (`BEHAVIOR.UNKNOWN_REVISION`) — rollback/lookup of a missing version.
- `InsufficientEvidenceException` (`BEHAVIOR.INSUFFICIENT_EVIDENCE`) — a change supplied fewer
  approved evidences than the profile requires.
- `ImmutableRoleBindingException` (`BEHAVIOR.IMMUTABLE_ROLE_BINDING`) — an attempt to re-bind a
  profile/proposal/observation to a different role.
- `InvalidProfileTransitionException` (`BEHAVIOR.INVALID_PROFILE_TRANSITION`) — a profile lifecycle
  operation attempted from a forbidding status.
- `InvalidProposalTransitionException` (`BEHAVIOR.INVALID_PROPOSAL_TRANSITION`) — a decision on a
  proposal that is no longer pending.
- `NoBehaviorChangeException` (`BEHAVIOR.NO_CHANGE`) — an "approved change" that would not change
  the traits.
- `ElevatedRiskNotAllowedException` (`BEHAVIOR.ELEVATED_RISK_NOT_ALLOWED`) — adopting elevated risk
  tolerance without a policy allowance.

**Dependencies.** PHP (`\RuntimeException`) and, for message construction, the Behavior enums. No I/O.

**Public interfaces.** `BehaviorDomainException::errorCode()`; per-type `CODE` constants and static
factory methods that build a well-formed instance.
