<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Base for immutable Data Transfer Objects. Data crossing a layer boundary is a
 * typed DTO, never a raw request array (docs/47 EAS-4). Concrete DTOs declare
 * `public readonly` promoted properties and a static `fromArray()` factory.
 */
abstract class DataTransferObject
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @return array<string,mixed>
     */
    public function only(string ...$keys): array
    {
        return array_intersect_key($this->toArray(), array_flip($keys));
    }

    /**
     * @return array<string,mixed>
     */
    public function except(string ...$keys): array
    {
        return array_diff_key($this->toArray(), array_flip($keys));
    }
}
