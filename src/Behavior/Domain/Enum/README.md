# Behavior\Domain\Enum

**Purpose.** The closed vocabularies of the Behavior domain: the fourteen style axes that make up a
behavior profile, plus the lifecycle and provenance enumerations. All are native, string-backed
enums so their values persist stably in JSONB trait columns and read models.

**Responsibilities.**
- Style-trait axes (14): `DecisionStyle`, `CommunicationStyle`, `ApprovalStyle`, `EscalationStyle`,
  `RiskTolerance`, `PriorityStrategy`, `DelegationStrategy`, `PlanningStrategy`, `FollowUpStrategy`,
  `DocumentationStyle`, `MeetingStyle`, `NegotiationStyle`, `CustomerInteractionStyle`,
  `QualityExpectation`. Each is one field of `ValueObject\BehaviorTraits`.
- Lifecycle: `ProfileStatus` (Draft/Active/Superseded/Archived) with `acceptsChanges()`;
  `ProposalStatus` (Pending/Approved/Rejected/Withdrawn) with `isPending()`.
- Provenance: `ObservationSourceType` — the kinds of approved practice an observation may cite.
- Axis registry: `BehaviorTraitAxis` — the fourteen style axes as a first-class, enumerable list,
  each knowing its backing style enum. This lets the domain treat the axes as data (read an axis by
  name, coerce a persisted string to its enum, iterate all axes), which is what makes deterministic
  per-axis consolidation (majority/weighted voting) and per-axis evidence observations possible.

**Dependencies.** PHP + `Nizam\Platform\Exception` (`BehaviorTraitAxis::toEnum()` throws
`InvalidArgumentException` on an out-of-vocabulary value).

**Public interfaces.** The enums above and their small helper methods
(`RiskTolerance::requiresElevatedRiskPolicy()`, `ProfileStatus::acceptsChanges()`,
`ProposalStatus::isPending()`, `BehaviorTraitAxis::enumClass()`/`toEnum()`/`accepts()`).
