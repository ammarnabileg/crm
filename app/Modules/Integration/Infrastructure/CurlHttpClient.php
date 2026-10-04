<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Infrastructure;

use HaHireAI\Modules\Integration\Contracts\HttpClient;
use HaHireAI\Modules\Integration\Domain\HttpClientResponse;

/**
 * cURL-backed outbound HTTP client used for real webhook delivery. Transport
 * errors are returned as a response with an `error`, never thrown — the caller
 * records the failed delivery and moves on.
 */
final class CurlHttpClient implements HttpClient
{
    public function post(string $url, string $body, array $headers = [], int $timeoutSeconds = 10): HttpClientResponse
    {
        if (! function_exists('curl_init')) {
            return new HttpClientResponse(0, '', 'cURL extension not available');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $responseBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = $responseBody === false ? (curl_error($ch) ?: 'request failed') : null;
        curl_close($ch);

        return new HttpClientResponse($status, is_string($responseBody) ? $responseBody : '', $error);
    }
}
