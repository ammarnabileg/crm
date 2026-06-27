<?php

declare(strict_types=1);

namespace App\Contracts\Http;

/**
 * An immutable HTTP response. `transportError` is non-null only when the request
 * never produced an HTTP status (DNS/connect/TLS failure or a timeout) — those are
 * distinguished from HTTP error statuses (401/429/5xx) so callers can react
 * precisely (retry vs surface "invalid key" vs "rate limited").
 */
final class HttpResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body = '',
        public readonly ?string $transportError = null,
    ) {
    }

    public function ok(): bool
    {
        return $this->transportError === null && $this->status >= 200 && $this->status < 300;
    }

    /** @return array<string,mixed> decoded JSON body, or [] when not decodable. */
    public function json(): array
    {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : [];
    }
}
