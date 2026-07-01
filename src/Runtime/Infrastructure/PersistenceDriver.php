<?php

declare(strict_types=1);

namespace Nizam\Runtime\Infrastructure;

/**
 * Selects which persistence adapters {@see RuntimeServiceProvider} binds the Runtime's execution ports to.
 *
 * The choice is explicit rather than inferred: the platform container autowires any concrete class
 * (including {@see \PDO}) by reflection, so "is a PDO available?" cannot be answered by container
 * introspection alone. A host therefore states its intent — durable PDO storage or ephemeral in-memory
 * storage — when it installs the Runtime.
 */
enum PersistenceDriver
{
    /**
     * Bind the in-memory execution repository, event store, and lock manager: real, tenant-scoped,
     * non-durable. The default for tests and for running the Runtime without a database.
     */
    case InMemory;

    /**
     * Bind the PDO execution adapters, backed by the shared {@see \PDO} connection the host must also
     * bind in the container. Durable storage for SQLite and PostgreSQL.
     */
    case Pdo;
}
