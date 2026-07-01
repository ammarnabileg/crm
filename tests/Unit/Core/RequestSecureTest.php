<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Core;

use HaHireAI\Core\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Scheme detection behind proxies is security-sensitive: forwarded headers are
 * client-spoofable, so they must only count when the app explicitly trusts its
 * proxy. These tests pin that contract.
 */
final class RequestSecureTest extends TestCase
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $server
     */
    private function request(array $headers = [], array $server = []): Request
    {
        return new Request('GET', '/', [], [], $headers, $server, []);
    }

    public function test_direct_https_is_secure(): void
    {
        $this->assertTrue($this->request(server: ['HTTPS' => 'on'])->isSecure());
    }

    public function test_https_off_is_not_secure(): void
    {
        $this->assertFalse($this->request(server: ['HTTPS' => 'off'])->isSecure());
    }

    public function test_port_443_is_secure(): void
    {
        $this->assertTrue($this->request(server: ['SERVER_PORT' => '443'])->isSecure());
    }

    public function test_plain_http_is_not_secure(): void
    {
        $this->assertFalse($this->request(server: ['SERVER_PORT' => '80'])->isSecure());
    }

    public function test_forwarded_proto_ignored_when_proxy_not_trusted(): void
    {
        $request = $this->request(headers: ['x-forwarded-proto' => 'https']);

        $this->assertFalse($request->isSecure(false), 'spoofable header must be ignored by default');
    }

    public function test_forwarded_proto_trusted_when_proxy_trusted(): void
    {
        $request = $this->request(headers: ['x-forwarded-proto' => 'https']);

        $this->assertTrue($request->isSecure(true));
    }

    public function test_forwarded_proto_list_uses_leftmost(): void
    {
        $request = $this->request(headers: ['x-forwarded-proto' => 'https, http']);

        $this->assertTrue($request->isSecure(true));
    }

    public function test_forwarded_ssl_on_is_trusted(): void
    {
        $this->assertTrue($this->request(headers: ['x-forwarded-ssl' => 'on'])->isSecure(true));
    }

    public function test_forwarded_port_443_is_trusted(): void
    {
        $this->assertTrue($this->request(headers: ['x-forwarded-port' => '443'])->isSecure(true));
    }

    public function test_cloudflare_visitor_https_is_trusted(): void
    {
        $request = $this->request(headers: ['cf-visitor' => '{"scheme":"https"}']);

        $this->assertTrue($request->isSecure(true));
        $this->assertFalse($request->isSecure(false), 'still ignored when proxy untrusted');
    }

    public function test_cloudflare_visitor_http_is_not_secure(): void
    {
        $request = $this->request(headers: ['cf-visitor' => '{"scheme":"http"}']);

        $this->assertFalse($request->isSecure(true));
    }
}
