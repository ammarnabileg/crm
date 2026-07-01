<?php

declare(strict_types=1);

namespace Nizam\Kernel\Domain;

/**
 * A domain value defined entirely by its attributes rather than an identity.
 *
 * Value objects are immutable and compared structurally: two value objects are interchangeable
 * when {@see self::equals()} returns true. Contrast with {@see Entity}, which is compared by id.
 */
interface ValueObject
{
    /**
     * Whether this value is structurally equal to another value of the same type.
     */
    public function equals(self $other): bool;
}
