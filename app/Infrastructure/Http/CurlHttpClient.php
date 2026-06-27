<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Contracts\Http\HttpClient;
use App\Contracts\Http\HttpResponse;

/**
 * cURL-backed HttpClient used in production. It never throws: connect/TLS failures
 * and timeouts come back as an HttpResponse with `transportError` set (status 0),
 * so AI provider adapters can map them to clean AiResult failures and the gateway
 * can fall back to the next provider.
 */
final class CurlHttpClient implements HttpClient
{
    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 60): HttpResponse
    {
        if (! function_exists('curl_init')) {
            return new HttpResponse(0, '', 'curl_unavailable');
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => max(1, $timeout),
            CURLOPT_CONNECTTIMEOUT => max(1, (int) min($timeout, 15)),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $responseBody === false) {
            // 28 = CURLE_OPERATION_TIMEDOUT. Everything else is a generic network error.
            $transport = $errno === 28 ? 'timeout' : 'network_error';

            return new HttpResponse(0, '', $transport);
        }

        return new HttpResponse($status, (string) $responseBody);
    }
}
