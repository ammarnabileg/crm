<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Infrastructure;

/**
 * Selects which persistence adapter {@see PluginServiceProvider} binds the plugin repository to.
 *
 * The choice is explicit rather than inferred: the platform container autowires any concrete class
 * (including {@see \PDO}) by reflection, so "is a PDO available?" cannot be answered by container
 * introspection alone. A host therefore states its intent — durable PDO storage or ephemeral in-memory
 * storage — when it installs the module.
 */
enum PersistenceDriver
{
    /**
     * Bind the in-memory repository: real, seedable, non-durable. The default for tests and for
     * running the module without a database.
     */
    case InMemory;

    /**
     * Bind the PDO repository, backed by the shared {@see \PDO} connection the host must also bind in
     * the container. Durable storage for SQLite and PostgreSQL.
     */
    case Pdo;
}
