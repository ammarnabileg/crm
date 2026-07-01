# Behavior\Infrastructure\Persistence\Pdo

**Purpose.** Durable PDO adapters for the Behavior persistence and read ports, working identically on
SQLite and PostgreSQL. Aggregates are stored one row each, with their trait sets, revision histories,
and evidence held as JSON documents (JSONB in Postgres, TEXT-encoded JSON in SQLite).

**Responsibilities.**
- `BehaviorMapper` — the single translation point between aggregates and their persisted scalar/JSON
  form; hydrates through the aggregates' `reconstitute()` factories (no events on load) and honors
  already-persisted elevated risk via the policy-allowance factory.
- `PdoBehaviorProfileRepository` — `save` (portable update-else-insert upsert), `ofId`, `ofRole`,
  `existsForRole`, `nextIdentity`; every read filters on `tenant_id` and `deleted_at IS NULL`.
- `PdoBehaviorChangeProposalRepository` — `save` (upsert), `ofId`, `pendingForTenant`
  (status + `proposed_at` order), `nextIdentity`; tenant-scoped, soft-delete aware.
- `PdoObservationSource` — reads only approved observations (`approved_at IS NOT NULL`) for a
  tenant/role since an optional instant; a `record()` writer lets fixtures seed through the same
  schema.

All statements are parameterized; the adapters never interpolate values into SQL.

**Dependencies.** The Behavior `Domain`; `Nizam\Kernel\Domain\TenantId`; `Nizam\Platform\Support\Json`;
`Nizam\Platform\Exception\PlatformException`; PHP `PDO`. The runnable schema lives in
`../../Migration/SqliteSchema.php` (and the Postgres source of record beside it).

**Public interfaces.** Implements `BehaviorProfileRepository`, `BehaviorChangeProposalRepository`, and
`ObservationSource`. `BehaviorMapper` is shared infrastructure used by all three adapters.
