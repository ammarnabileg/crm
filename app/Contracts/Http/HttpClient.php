<?php

declare(strict_types=1);

namespace App\Contracts\Http;

/**
 * A minimal outbound HTTP client (the seam that lets AI provider adapters reach
 * OpenAI/Claude/Gemini/… while staying unit-testable offline via a fake transport).
 * Implementations MUST NOT throw on a transport failure — they return an
 * HttpResponse carrying `transportError` so callers can map it to a clean result.
 */
interface HttpClient
{
    /**
     * @param array<int,string> $headers raw header lines ("Name: value")
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 60): HttpResponse;
}
