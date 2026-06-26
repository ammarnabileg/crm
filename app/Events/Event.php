<?php

declare(strict_types=1);

namespace App\Events;

/**
 * Marker base class for domain events. Events are immutable value objects
 * carrying the data listeners need; they perform no work themselves.
 */
abstract class Event
{
    public readonly string $occurredAt;

    public function __construct()
    {
        $this->occurredAt = now();
    }
}
