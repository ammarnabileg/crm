<?php

declare(strict_types=1);

namespace Nizam\Kernel\Domain;

/**
 * A domain object with a distinct, stable identity that persists across state changes.
 *
 * Entities are compared by identity, not by attribute values: two entities are the same when their
 * {@see Identifier}s are equal, regardless of any other field. Subclasses supply the concrete id
 * type via the constructor. Contrast with {@see ValueObject}, which is compared structurally.
 */
abstract class Entity
{
    /**
     * @param Identifier $id The entity's identity.
     */
    protected function __construct(protected readonly Identifier $id)
    {
    }

    /**
     * The entity's identifier.
     */
    public function id(): Identifier
    {
        return $this->id;
    }

    /**
     * Whether this entity is the same as another, by identity.
     */
    public function sameIdentityAs(self $other): bool
    {
        return $other::class === static::class && $other->id->equals($this->id);
    }
}
