<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Domain;

/**
 * The result of an HTTP GET. `ok` means we received a 2xx; `error` is set when
 * the transport failed (DNS, timeout, blocked egress) — in which case body is
 * empty and status is 0. Never thrown — returned, so callers degrade gracefully.
 */
final class HttpFetchResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly ?string $error = null,
    ) {
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300 && $this->error === null;
    }

    /** Decode a JSON body to an array, or null if not decodable. */
    public function json(): ?array
    {
        if ($this->body === '') {
            return null;
        }
        $data = json_decode($this->body, true);

        return is_array($data) ? $data : null;
    }

    public static function failed(string $error): self
    {
        return new self(0, '', $error);
    }
}
