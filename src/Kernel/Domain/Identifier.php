<?php

declare(strict_types=1);

namespace Nizam\Kernel\Domain;

use Nizam\Platform\Exception\InvalidArgumentException;
use Nizam\Platform\Support\Uuid;
use Stringable;

/**
 * Base class for immutable, UUID v7-backed identifiers (e.g. {@see TenantId}, {@see UserId}).
 *
 * A typed identifier prevents mixing ids of different aggregates at the type level while sharing
 * one implementation: generation, validation, string conversion and equality. The wrapped value is
 * always a syntactically valid UUID; construction of an invalid id fails fast. Instances are
 * immutable — the readonly value never changes after construction.
 */
abstract class Identifier implements Stringable, ValueObject
{
    /**
     * @param string $value A valid UUID string.
     *
     * @throws InvalidArgumentException When $value is not a valid UUID.
     */
    final protected function __construct(private readonly string $value)
    {
        if (!Uuid::isValid($value)) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a valid UUID for identifier %s.',
                $value,
                static::class,
            ));
        }
    }

    /**
     * Mint a brand-new identifier backed by a fresh UUID v7.
     *
     * @return static
     */
    public static function generate(): static
    {
        return new static(Uuid::v7());
    }

    /**
     * Rehydrate an identifier from its string form (e.g. from the database).
     *
     * @return static
     *
     * @throws InvalidArgumentException When the string is not a valid UUID.
     */
    public static function fromString(string $value): static
    {
        return new static($value);
    }

    /**
     * The underlying UUID string.
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Whether this identifier equals another.
     *
     * Two identifiers are equal only when they are the same concrete type and carry the same
     * UUID, so a {@see TenantId} can never equal a {@see UserId} with the same string value.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof static
            && $other::class === static::class
            && $other->value === $this->value;
    }

    /**
     * String representation (the UUID) for logging and interpolation.
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
