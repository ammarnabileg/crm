-- =============================================================================
-- Behavior bounded context — schema (PostgreSQL 16)
-- =============================================================================
-- Design source of record for the Behavior module's persistent state. The
-- runnable SQLite equivalent used by integration tests lives alongside this file
-- in SqliteSchema.php and MUST be kept structurally in step with it.
--
-- Conventions (platform-wide):
--   * Primary keys are UUIDv7 values minted in the application layer.
--   * Every table is tenant-scoped via a NOT NULL tenant_id and soft-delete aware
--     via a nullable deleted_at; reads filter (tenant_id, ... , deleted_at IS NULL).
--   * Audit columns: created_at/updated_at (timestamptz) + created_by/updated_by.
--   * version is the aggregate's optimistic-concurrency counter.
--   * Trait sets, revision history and evidence are stored as JSONB documents,
--     encoded/decoded by BehaviorMapper.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- behavior_profiles — the versioned, role-bound behavior profile aggregate.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS behavior_profiles (
    id              UUID        NOT NULL,
    tenant_id       UUID        NOT NULL,
    role_id         UUID        NOT NULL,
    status          VARCHAR(32) NOT NULL,
    current_version INTEGER     NOT NULL,
    current_traits  JSONB       NOT NULL,
    revisions       JSONB       NOT NULL,
    created_at      TIMESTAMPTZ NOT NULL,
    updated_at      TIMESTAMPTZ NOT NULL,
    created_by      VARCHAR(255) NOT NULL,
    updated_by      VARCHAR(255) NOT NULL,
    deleted_at      TIMESTAMPTZ NULL,
    version         INTEGER     NOT NULL DEFAULT 0,
    CONSTRAINT pk_behavior_profiles PRIMARY KEY (id),
    CONSTRAINT chk_behavior_profiles_current_version_positive CHECK (current_version >= 1),
    CONSTRAINT chk_behavior_profiles_version_nonnegative CHECK (version >= 0)
);

-- One live profile per (tenant, role): a role is bound to a single behavior profile.
CREATE UNIQUE INDEX IF NOT EXISTS uq_behavior_profiles_tenant_role
    ON behavior_profiles (tenant_id, role_id)
    WHERE deleted_at IS NULL;

-- Primary lookup path: profiles of a tenant/role, excluding soft-deleted rows.
CREATE INDEX IF NOT EXISTS idx_behavior_profiles_tenant_role
    ON behavior_profiles (tenant_id, role_id)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_behavior_profiles_tenant_status
    ON behavior_profiles (tenant_id, status)
    WHERE deleted_at IS NULL;

-- -----------------------------------------------------------------------------
-- behavior_profile_revisions — the append-only, immutable revision history.
-- -----------------------------------------------------------------------------
-- The aggregate also embeds its revisions as JSONB on behavior_profiles for
-- single-read hydration; this normalized table is the durable, queryable audit
-- ledger. Rows are never updated or deleted (history is append-only).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS behavior_profile_revisions (
    id           UUID        NOT NULL,
    tenant_id    UUID        NOT NULL,
    profile_id   UUID        NOT NULL,
    version      INTEGER     NOT NULL,
    traits       JSONB       NOT NULL,
    change_log   JSONB       NOT NULL,
    evidence     JSONB       NOT NULL,
    approved_by  VARCHAR(255) NOT NULL,
    approved_at  TIMESTAMPTZ NOT NULL,
    created_at   TIMESTAMPTZ NOT NULL,
    created_by   VARCHAR(255) NOT NULL,
    deleted_at   TIMESTAMPTZ NULL,
    CONSTRAINT pk_behavior_profile_revisions PRIMARY KEY (id),
    CONSTRAINT fk_behavior_profile_revisions_profile
        FOREIGN KEY (profile_id) REFERENCES behavior_profiles (id) ON DELETE CASCADE,
    CONSTRAINT uq_behavior_profile_revisions_profile_version UNIQUE (profile_id, version),
    CONSTRAINT chk_behavior_profile_revisions_version_positive CHECK (version >= 1)
);

CREATE INDEX IF NOT EXISTS idx_behavior_profile_revisions_tenant_profile
    ON behavior_profile_revisions (tenant_id, profile_id)
    WHERE deleted_at IS NULL;

-- -----------------------------------------------------------------------------
-- behavior_change_proposals — the reviewable, approval-gated change request.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS behavior_change_proposals (
    id                  UUID        NOT NULL,
    tenant_id           UUID        NOT NULL,
    role_id             UUID        NOT NULL,
    profile_id          UUID        NOT NULL,
    proposed_traits     JSONB       NOT NULL,
    rationale           TEXT        NOT NULL,
    supporting_evidence JSONB       NOT NULL,
    confidence          DOUBLE PRECISION NOT NULL,
    business_impact     TEXT        NOT NULL,
    rollback_to_version INTEGER     NULL,
    status              VARCHAR(32) NOT NULL,
    proposed_by         VARCHAR(255) NOT NULL,
    proposed_at         TIMESTAMPTZ NOT NULL,
    decided_by          VARCHAR(255) NULL,
    decided_at          TIMESTAMPTZ NULL,
    created_at          TIMESTAMPTZ NOT NULL,
    updated_at          TIMESTAMPTZ NOT NULL,
    created_by          VARCHAR(255) NOT NULL,
    updated_by          VARCHAR(255) NOT NULL,
    deleted_at          TIMESTAMPTZ NULL,
    version             INTEGER     NOT NULL DEFAULT 1,
    CONSTRAINT pk_behavior_change_proposals PRIMARY KEY (id),
    CONSTRAINT fk_behavior_change_proposals_profile
        FOREIGN KEY (profile_id) REFERENCES behavior_profiles (id) ON DELETE CASCADE,
    CONSTRAINT chk_behavior_change_proposals_confidence_range
        CHECK (confidence >= 0 AND confidence <= 1),
    CONSTRAINT chk_behavior_change_proposals_rollback_positive
        CHECK (rollback_to_version IS NULL OR rollback_to_version >= 1)
);

-- Primary lookup path: pending proposals for a tenant, ordered by proposed_at.
CREATE INDEX IF NOT EXISTS idx_behavior_change_proposals_tenant_status
    ON behavior_change_proposals (tenant_id, status, proposed_at)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_behavior_change_proposals_tenant_profile
    ON behavior_change_proposals (tenant_id, profile_id)
    WHERE deleted_at IS NULL;

-- -----------------------------------------------------------------------------
-- behavior_observations — approved business practice that may inform behavior.
-- -----------------------------------------------------------------------------
-- Only approved rows (approved_at IS NOT NULL) drive behavior evolution; the read
-- adapter filters on that. One evidence reference is flattened per row.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS behavior_observations (
    id           UUID        NOT NULL,
    tenant_id    UUID        NOT NULL,
    role_id      UUID        NOT NULL,
    source_type  VARCHAR(64) NOT NULL,
    reference_id VARCHAR(255) NOT NULL,
    summary      TEXT        NOT NULL,
    occurred_at  TIMESTAMPTZ NOT NULL,
    weight       DOUBLE PRECISION NOT NULL,
    observed_traits JSONB    NOT NULL DEFAULT '{}'::jsonb,
    approved_by  VARCHAR(255) NULL,
    approved_at  TIMESTAMPTZ NULL,
    created_at   TIMESTAMPTZ NOT NULL,
    created_by   VARCHAR(255) NOT NULL,
    deleted_at   TIMESTAMPTZ NULL,
    CONSTRAINT pk_behavior_observations PRIMARY KEY (id),
    CONSTRAINT chk_behavior_observations_weight_range CHECK (weight >= 0 AND weight <= 1)
);

-- Primary lookup path: approved observations for a tenant/role, by occurrence time.
CREATE INDEX IF NOT EXISTS idx_behavior_observations_tenant_role_approved
    ON behavior_observations (tenant_id, role_id, occurred_at)
    WHERE deleted_at IS NULL AND approved_at IS NOT NULL;
