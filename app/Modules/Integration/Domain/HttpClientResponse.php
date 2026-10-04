<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Domain;

/** The result of an outbound HTTP call (or a transport failure). */
final class HttpClientResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body = '',
        public readonly ?string $error = null,
    ) {
    }

    /** A 2xx response with no transport error. */
    public function successful(): bool
    {
        return $this->error === null && $this->status >= 200 && $this->status < 300;
    }
}
