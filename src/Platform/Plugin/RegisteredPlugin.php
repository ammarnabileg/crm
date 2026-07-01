<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin;

use DateTimeImmutable;
use Nizam\Kernel\Domain\AggregateRoot;
use Nizam\Kernel\Domain\Clock;
use Nizam\Kernel\Domain\TenantId;
use Nizam\Platform\Plugin\Event\PluginDisabled;
use Nizam\Platform\Plugin\Event\PluginEnabled;
use Nizam\Platform\Plugin\Event\PluginFailed;
use Nizam\Platform\Plugin\Event\PluginInstalled;
use Nizam\Platform\Plugin\Event\PluginUninstalled;
use Nizam\Platform\Plugin\Event\PluginUpdated;
use Nizam\Platform\Plugin\Exception\PluginStateException;
use Nizam\Platform\Support\Assert;

/**
 * A plugin as it exists in the registry: its identity, manifest, lifecycle state and health.
 *
 * The registered plugin is the aggregate that owns a plugin's lifecycle. Its {@see PluginState} is a
 * guarded state machine — a plugin may only be installed from {@see PluginState::Discovered}, enabled
 * from installed/disabled, disabled from enabled, updated to a strictly newer version, marked failed
 * or incompatible from any live state, and uninstalled once (a terminal transition). Every legal
 * transition records the corresponding domain event for the application layer to publish after the
 * unit of work commits; every illegal transition raises {@see PluginStateException} and changes
 * nothing. The plugin may be tenant-scoped (installed for one tenant) or global (`tenantId` null).
 */
final class RegisteredPlugin extends AggregateRoot
{
    /**
     * @param PluginId               $id          The plugin's identity.
     * @param PluginManifest         $manifest    The plugin's descriptor.
     * @param PluginState            $state       The lifecycle state.
     * @param TenantId|null          $tenantId    The owning tenant, or null when global.
     * @param string                 $source      The source locator the plugin was installed from.
     * @param DateTimeImmutable      $installedAt When the plugin was installed.
     * @param DateTimeImmutable|null $enabledAt   When the plugin was last enabled, or null.
     * @param DateTimeImmutable      $updatedAt   When the plugin record last changed.
     * @param PluginHealthStatus|null $lastHealth The last-known health status, or null.
     * @param string|null            $failureReason The reason for the current failed/incompatible state, or null.
     * @param int                    $version     Optimistic-concurrency version of the aggregate.
     */
    private function __construct(
        PluginId $id,
        private PluginManifest $manifest,
        private PluginState $state,
        private readonly ?TenantId $tenantId,
        private string $source,
        private readonly DateTimeImmutable $installedAt,
        private ?DateTimeImmutable $enabledAt,
        private DateTimeImmutable $updatedAt,
        private ?PluginHealthStatus $lastHealth,
        private ?string $failureReason,
        private int $version,
    ) {
        parent::__construct($id);
    }

    /**
     * Install a discovered plugin into the registry.
     *
     * Creates the record in {@see PluginState::Installed} and records a {@see PluginInstalled} event.
     * The plugin is not yet enabled and does not participate until {@see self::enable()} is called.
     *
     * @param PluginId       $id       The identity to assign.
     * @param PluginManifest $manifest The plugin's descriptor.
     * @param string         $source   The source locator the plugin was installed from.
     * @param TenantId|null  $tenantId The owning tenant, or null for a global install.
     * @param Clock          $clock    Time source.
     */
    public static function install(
        PluginId $id,
        PluginManifest $manifest,
        string $source,
        ?TenantId $tenantId,
        Clock $clock,
    ): self {
        Assert::notEmpty($source, 'A registered plugin must record its source.');

        $now = $clock->now();
        $plugin = new self(
            id: $id,
            manifest: $manifest,
            state: PluginState::Installed,
            tenantId: $tenantId,
            source: $source,
            installedAt: $now,
            enabledAt: null,
            updatedAt: $now,
            lastHealth: null,
            failureReason: null,
            version: 0,
        );

        $plugin->recordThat(new PluginInstalled(
            $id,
            $manifest->name(),
            (string) $manifest->version(),
            $tenantId,
            $now,
        ));

        return $plugin;
    }

    /**
     * Reconstitute a registered plugin from persisted state without emitting events.
     *
     * Used by repositories when hydrating; performs the same structural invariants as construction but
     * records no domain event.
     */
    public static function reconstitute(
        PluginId $id,
        PluginManifest $manifest,
        PluginState $state,
        ?TenantId $tenantId,
        string $source,
        DateTimeImmutable $installedAt,
        ?DateTimeImmutable $enabledAt,
        DateTimeImmutable $updatedAt,
        ?PluginHealthStatus $lastHealth,
        ?string $failureReason,
        int $version,
    ): self {
        Assert::that($version >= 0, 'The aggregate version must not be negative.');

        return new self(
            id: $id,
            manifest: $manifest,
            state: $state,
            tenantId: $tenantId,
            source: $source,
            installedAt: $installedAt,
            enabledAt: $enabledAt,
            updatedAt: $updatedAt,
            lastHealth: $lastHealth,
            failureReason: $failureReason,
            version: $version,
        );
    }

    /**
     * Enable the plugin so it begins actively participating.
     *
     * Legal from {@see PluginState::Installed}, {@see PluginState::Disabled} or {@see PluginState::Failed}
     * (re-enabling clears the failure). Records a {@see PluginEnabled} event.
     *
     * @param Clock $clock Time source.
     *
     * @throws PluginStateException When the plugin cannot be enabled from its current state.
     */
    public function enable(Clock $clock): void
    {
        if (!in_array($this->state, [PluginState::Installed, PluginState::Disabled, PluginState::Failed], true)) {
            throw PluginStateException::forOperation('enable', $this->state);
        }

        $now = $clock->now();
        $this->state = PluginState::Enabled;
        $this->enabledAt = $now;
        $this->failureReason = null;
        $this->touch($now);

        $this->recordThat(new PluginEnabled(
            $this->pluginId(),
            $this->manifest->name(),
            $this->tenantId,
            $now,
        ));
    }

    /**
     * Disable the plugin so it stops participating, without uninstalling it.
     *
     * Legal only from {@see PluginState::Enabled}. Records a {@see PluginDisabled} event.
     *
     * @param Clock $clock Time source.
     *
     * @throws PluginStateException When the plugin is not currently enabled.
     */
    public function disable(Clock $clock): void
    {
        if ($this->state !== PluginState::Enabled) {
            throw PluginStateException::forOperation('disable', $this->state);
        }

        $now = $clock->now();
        $this->state = PluginState::Disabled;
        $this->touch($now);

        $this->recordThat(new PluginDisabled(
            $this->pluginId(),
            $this->manifest->name(),
            $this->tenantId,
            $now,
        ));
    }

    /**
     * Replace the plugin's manifest with a strictly newer version.
     *
     * The new manifest must have the same name and a version strictly greater than the current one.
     * Records a {@see PluginUpdated} event carrying both versions. The lifecycle state is preserved
     * (an enabled plugin stays enabled across the update).
     *
     * @param PluginManifest $newManifest The newer manifest to adopt.
     * @param string         $newSource   The source locator the newer version came from.
     * @param Clock          $clock       Time source.
     *
     * @throws PluginStateException When names differ, the version is not newer, or the plugin is terminal.
     */
    public function update(PluginManifest $newManifest, string $newSource, Clock $clock): void
    {
        Assert::notEmpty($newSource, 'An update must record its source.');

        if ($this->state->isTerminal()) {
            throw PluginStateException::forOperation('update', $this->state);
        }
        if ($newManifest->name() !== $this->manifest->name()) {
            throw PluginStateException::forOperation('update to a different-named plugin', $this->state);
        }
        if (!$newManifest->version()->isGreaterThan($this->manifest->version())) {
            throw PluginStateException::forOperation('update to a non-newer version of', $this->state);
        }

        $now = $clock->now();
        $previousVersion = (string) $this->manifest->version();
        $this->manifest = $newManifest;
        $this->source = $newSource;
        $this->touch($now);

        $this->recordThat(new PluginUpdated(
            $this->pluginId(),
            $newManifest->name(),
            $previousVersion,
            (string) $newManifest->version(),
            $this->tenantId,
            $now,
        ));
    }

    /**
     * Mark the plugin failed, recording why.
     *
     * Legal from any non-terminal state. Records a {@see PluginFailed} event; the plugin may later be
     * re-enabled, which clears the failure.
     *
     * @param string $reason The human-readable failure reason.
     * @param Clock  $clock  Time source.
     *
     * @throws PluginStateException When the plugin is already uninstalled.
     */
    public function markFailed(string $reason, Clock $clock): void
    {
        Assert::notEmpty($reason, 'A failure must record a reason.');

        if ($this->state->isTerminal()) {
            throw PluginStateException::forOperation('mark as failed', $this->state);
        }

        $now = $clock->now();
        $this->state = PluginState::Failed;
        $this->failureReason = $reason;
        $this->touch($now);

        $this->recordThat(new PluginFailed(
            $this->pluginId(),
            $this->manifest->name(),
            $reason,
            $this->tenantId,
            $now,
        ));
    }

    /**
     * Mark the plugin incompatible with the current platform, recording why.
     *
     * Legal from any non-terminal state. Records a {@see PluginFailed} event with the incompatibility
     * reason (incompatibility is a fault mode of the plugin from the platform's perspective).
     *
     * @param string $reason The human-readable incompatibility reason.
     * @param Clock  $clock  Time source.
     *
     * @throws PluginStateException When the plugin is already uninstalled.
     */
    public function markIncompatible(string $reason, Clock $clock): void
    {
        Assert::notEmpty($reason, 'An incompatibility must record a reason.');

        if ($this->state->isTerminal()) {
            throw PluginStateException::forOperation('mark as incompatible', $this->state);
        }

        $now = $clock->now();
        $this->state = PluginState::Incompatible;
        $this->failureReason = $reason;
        $this->touch($now);

        $this->recordThat(new PluginFailed(
            $this->pluginId(),
            $this->manifest->name(),
            $reason,
            $this->tenantId,
            $now,
        ));
    }

    /**
     * Uninstall the plugin, retiring it from the registry.
     *
     * A terminal transition, legal from any non-terminal state. Records a {@see PluginUninstalled}
     * event.
     *
     * @param Clock $clock Time source.
     *
     * @throws PluginStateException When the plugin is already uninstalled.
     */
    public function uninstall(Clock $clock): void
    {
        if ($this->state->isTerminal()) {
            throw PluginStateException::forOperation('uninstall', $this->state);
        }

        $now = $clock->now();
        $this->state = PluginState::Uninstalled;
        $this->enabledAt = null;
        $this->touch($now);

        $this->recordThat(new PluginUninstalled(
            $this->pluginId(),
            $this->manifest->name(),
            $this->tenantId,
            $now,
        ));
    }

    /**
     * Record the outcome of a health check on this plugin.
     *
     * Updates the last-known health; does not itself change lifecycle state.
     */
    public function recordHealth(PluginHealthStatus $status): void
    {
        $this->lastHealth = $status;
        $this->touch($status->checkedAt());
    }

    /**
     * The plugin's identity, narrowed to {@see PluginId}.
     */
    public function pluginId(): PluginId
    {
        $id = $this->id();
        assert($id instanceof PluginId);

        return $id;
    }

    /**
     * The plugin's descriptor.
     */
    public function manifest(): PluginManifest
    {
        return $this->manifest;
    }

    /**
     * The plugin's manifest name.
     */
    public function name(): string
    {
        return $this->manifest->name();
    }

    /**
     * The lifecycle state.
     */
    public function state(): PluginState
    {
        return $this->state;
    }

    /**
     * The capability kind of the plugin.
     */
    public function kind(): PluginKind
    {
        return $this->manifest->kind();
    }

    /**
     * Whether the plugin is currently enabled.
     */
    public function isEnabled(): bool
    {
        return $this->state->isActive();
    }

    /**
     * The owning tenant, or null when the plugin is global.
     */
    public function tenantId(): ?TenantId
    {
        return $this->tenantId;
    }

    /**
     * Whether the plugin is scoped to a specific tenant.
     */
    public function isGlobal(): bool
    {
        return $this->tenantId === null;
    }

    /**
     * The source locator the plugin was installed from.
     */
    public function source(): string
    {
        return $this->source;
    }

    /**
     * When the plugin was installed.
     */
    public function installedAt(): DateTimeImmutable
    {
        return $this->installedAt;
    }

    /**
     * When the plugin was last enabled, or null if never.
     */
    public function enabledAt(): ?DateTimeImmutable
    {
        return $this->enabledAt;
    }

    /**
     * When the plugin record last changed.
     */
    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * The last-known health status, or null if never checked.
     */
    public function lastHealth(): ?PluginHealthStatus
    {
        return $this->lastHealth;
    }

    /**
     * The reason for the current failed/incompatible state, or null.
     */
    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    /**
     * The optimistic-concurrency version of the aggregate.
     */
    public function version(): int
    {
        return $this->version;
    }

    /**
     * Advance the updated timestamp and the optimistic-concurrency version after a mutation.
     */
    private function touch(DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
        ++$this->version;
    }
}
