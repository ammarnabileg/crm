# Behavior\Domain\ValueObject

**Purpose.** The immutable, structurally-compared values that describe behavior and its
justification. These carry no identity; two values with equal attributes are interchangeable.

**Responsibilities.**
- `BehaviorTraits` — the complete description of how a role works: fourteen style-enum axes plus
  `evidenceRequirements` (the minimum approved evidences a change must supply). Immutable, with a
  `with*()` copy per field, `diff()` (the explainable per-trait change map), `equals()`, and the
  elevated-risk factory guard (`create()` forbids elevated risk; `withPolicyAllowance()` permits it
  only when a policy allowance is asserted).
- `EvidenceReference` — a pointer to one piece of approved practice: source type, reference id,
  summary, when it occurred, and a normalized weight in [0, 1].
- `BehaviorRecommendation` — a fully explainable, single-trait suggestion: target trait, current and
  recommended values, reason, non-empty supporting evidence, confidence in [0, 1], and how to apply.
- `ChangeLogEntry` — the audit record of one versioned change: version, when/by whom, summary, the
  traits diff, business impact, and (for a rollback) the restored version.

**Dependencies.** `Nizam\Kernel\Domain\ValueObject`; `Nizam\Platform\Support\Assert`;
`Behavior\Domain\Enum\*`. No I/O.

**Public interfaces.** `BehaviorTraits`, `EvidenceReference`, `BehaviorRecommendation`,
`ChangeLogEntry` — each with typed accessors, `equals()`, and a scalar-only `toArray()` for
persistence and read models.
