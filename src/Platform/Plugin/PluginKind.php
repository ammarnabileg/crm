<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin;

/**
 * The kind of capability a plugin contributes to the platform.
 *
 * The Nizam platform treats *everything* as a plugin (ADR-0016): agents, tools, integrations and
 * whole departments are all installed, versioned and governed through the same substrate. A plugin's
 * kind fixes which SDK contract its entry-point class must implement (see the `Contract/` namespace)
 * and therefore how the platform is allowed to invoke it. String-backed for stable persistence in the
 * plugin registry and manifests.
 */
enum PluginKind: string
{
    /** A managing agent that plans and oversees other agents. */
    case Manager = 'manager';

    /** A team-leading agent that coordinates a group of workers. */
    case TeamLeader = 'team_leader';

    /** A worker agent that performs concrete units of work. */
    case Worker = 'worker';

    /** A callable tool an agent may invoke to act on the world. */
    case Tool = 'tool';

    /** An integration with an external system or third-party service. */
    case Integration = 'integration';

    /** An automation that reacts to events or runs on a schedule. */
    case Automation = 'automation';

    /** A whole department: a self-contained bundle of roles and capabilities. */
    case Department = 'department';

    /** A provider of an infrastructure capability (e.g. an LLM or storage backend). */
    case Provider = 'provider';

    /** A source of knowledge an agent may consult (e.g. a document corpus). */
    case KnowledgeSource = 'knowledge_source';

    /**
     * The fully-qualified interface a plugin of this kind must implement.
     *
     * Used by the validator to check, via reflection, that a manifest's `entryPointClass` actually
     * fulfils the contract its declared kind demands.
     *
     * @return class-string
     */
    public function contract(): string
    {
        return match ($this) {
            self::Manager => Contract\ManagerPlugin::class,
            self::TeamLeader => Contract\TeamLeaderPlugin::class,
            self::Worker => Contract\WorkerPlugin::class,
            self::Tool => Contract\ToolPlugin::class,
            self::Integration => Contract\IntegrationPlugin::class,
            self::Automation => Contract\AutomationPlugin::class,
            self::Department => Contract\DepartmentPlugin::class,
            self::Provider => Contract\ProviderPlugin::class,
            self::KnowledgeSource => Contract\KnowledgeSourcePlugin::class,
        };
    }

    /**
     * A human-readable label for this kind, for read models and UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Manager',
            self::TeamLeader => 'Team Leader',
            self::Worker => 'Worker',
            self::Tool => 'Tool',
            self::Integration => 'Integration',
            self::Automation => 'Automation',
            self::Department => 'Department',
            self::Provider => 'Provider',
            self::KnowledgeSource => 'Knowledge Source',
        };
    }
}
