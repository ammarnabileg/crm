-- =============================================================================
-- Plugin Platform — schema (PostgreSQL 16)
-- =============================================================================
-- Design source of record for the Plugin Platform's persistent state. The
-- runnable SQLite equivalent used by integration tests lives alongside this file
-- in SqliteSchema.php and MUST be kept structurally in step with it.
--
-- Conventions (platform-wide):
--   * Primary keys are UUIDv7 values minted in the application layer.
--   * tenant_id is NULLABLE here: a plugin may be installed for one tenant, or
--     globally (tenant_id IS NULL) for the whole platform (ADR-0016, ADR-0020).
--   * Soft-delete aware via a nullable deleted_at; live reads filter deleted_at
--     IS NULL. Uninstalling a plugin soft-deletes its registry row.
--   * Audit columns: created_at/updated_at (timestamptz) + created_by/updated_by.
--   * version is the aggregate's optimistic-concurrency counter.
--   * The published manifest is stored as a single JSONB document, encoded and
--     decoded by PluginHydrator.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- plugin_registry — the installed-plugin aggregate (one row per install).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS plugin_registry (
    id                UUID         NOT NULL,
    tenant_id         UUID         NULL,
    name              VARCHAR(255) NOT NULL,
    display_name      VARCHAR(255) NOT NULL,
    kind              VARCHAR(32)  NOT NULL,
    plugin_version    VARCHAR(64)  NOT NULL,
    state             VARCHAR(32)  NOT NULL,
    manifest          JSONB        NOT NULL,
    source            VARCHAR(1024) NOT NULL,
    failure_reason    TEXT         NULL,
    health_level      VARCHAR(32)  NULL,
    health_message    TEXT         NULL,
    health_checked_at TIMESTAMPTZ  NULL,
    installed_at      TIMESTAMPTZ  NOT NULL,
    enabled_at        TIMESTAMPTZ  NULL,
    updated_at        TIMESTAMPTZ  NOT NULL,
    created_at        TIMESTAMPTZ  NOT NULL,
    created_by        VARCHAR(255) NOT NULL,
    updated_by        VARCHAR(255) NOT NULL,
    deleted_at        TIMESTAMPTZ  NULL,
    version           INTEGER      NOT NULL DEFAULT 0,
    CONSTRAINT pk_plugin_registry PRIMARY KEY (id),
    CONSTRAINT chk_plugin_registry_version_nonnegative CHECK (version >= 0)
);

-- One live install per plugin name (a name identifies the plugin; re-installs and
-- uninstalled rows do not collide because uninstalled rows are soft-deleted).
CREATE UNIQUE INDEX IF NOT EXISTS uq_plugin_registry_name
    ON plugin_registry (name)
    WHERE deleted_at IS NULL;

-- Primary listing / routing lookups, excluding soft-deleted rows.
CREATE INDEX IF NOT EXISTS idx_plugin_registry_kind
    ON plugin_registry (kind)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_plugin_registry_state
    ON plugin_registry (state)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_plugin_registry_tenant
    ON plugin_registry (tenant_id)
    WHERE deleted_at IS NULL;

-- -----------------------------------------------------------------------------
-- plugin_versions — the append-only version ledger (install/update history).
-- -----------------------------------------------------------------------------
-- Every installed or updated version of a plugin is recorded here so an update
-- can be audited and rolled back to a prior version's manifest. Rows are never
-- updated (history is append-only); the current version is the one on
-- plugin_registry.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS plugin_versions (
    id             UUID         NOT NULL,
    plugin_id      UUID         NOT NULL,
    tenant_id      UUID         NULL,
    name           VARCHAR(255) NOT NULL,
    plugin_version VARCHAR(64)  NOT NULL,
    manifest       JSONB        NOT NULL,
    source         VARCHAR(1024) NOT NULL,
    is_current     BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at     TIMESTAMPTZ  NOT NULL,
    created_by     VARCHAR(255) NOT NULL,
    deleted_at     TIMESTAMPTZ  NULL,
    CONSTRAINT pk_plugin_versions PRIMARY KEY (id),
    CONSTRAINT uq_plugin_versions_plugin_version UNIQUE (plugin_id, plugin_version),
    CONSTRAINT fk_plugin_versions_plugin FOREIGN KEY (plugin_id)
        REFERENCES plugin_registry (id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_plugin_versions_plugin
    ON plugin_versions (plugin_id)
    WHERE deleted_at IS NULL;

-- -----------------------------------------------------------------------------
-- plugin_dependencies — the declared inter-plugin dependencies (queryable form).
-- -----------------------------------------------------------------------------
-- The manifest embeds its dependencies as JSONB for single-read hydration; this
-- normalized table is the durable, queryable dependency graph used to answer
-- "what depends on X" and to drive install ordering at scale.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS plugin_dependencies (
    id               UUID         NOT NULL,
    plugin_id        UUID         NOT NULL,
    tenant_id        UUID         NULL,
    dependency_name  VARCHAR(255) NOT NULL,
    version_constraint VARCHAR(128) NOT NULL,
    optional         BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at       TIMESTAMPTZ  NOT NULL,
    deleted_at       TIMESTAMPTZ  NULL,
    CONSTRAINT pk_plugin_dependencies PRIMARY KEY (id),
    CONSTRAINT fk_plugin_dependencies_plugin FOREIGN KEY (plugin_id)
        REFERENCES plugin_registry (id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_plugin_dependencies_plugin
    ON plugin_dependencies (plugin_id)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_plugin_dependencies_name
    ON plugin_dependencies (dependency_name)
    WHERE deleted_at IS NULL;

-- -----------------------------------------------------------------------------
-- plugin_health — the append-only ledger of health-check outcomes.
-- -----------------------------------------------------------------------------
-- Each run of a plugin's health check appends a row here; the most recent row is
-- also denormalized onto plugin_registry (health_level/message/checked_at) for
-- single-read display. Rows are never updated (history is append-only).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS plugin_health (
    id         UUID         NOT NULL,
    plugin_id  UUID         NOT NULL,
    tenant_id  UUID         NULL,
    level      VARCHAR(32)  NOT NULL,
    message    TEXT         NOT NULL,
    checked_at TIMESTAMPTZ  NOT NULL,
    created_at TIMESTAMPTZ  NOT NULL,
    deleted_at TIMESTAMPTZ  NULL,
    CONSTRAINT pk_plugin_health PRIMARY KEY (id),
    CONSTRAINT fk_plugin_health_plugin FOREIGN KEY (plugin_id)
        REFERENCES plugin_registry (id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_plugin_health_plugin_checked
    ON plugin_health (plugin_id, checked_at)
    WHERE deleted_at IS NULL;
