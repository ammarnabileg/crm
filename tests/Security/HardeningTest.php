<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Core\Middleware\SecurityHeaders;
use App\Core\Request;
use App\Core\Response;
use Tests\TestCase;

/**
 * Security & Hardening Bible regressions: the open-redirect guard on back() and the
 * hardening headers (nonce-based CSP with NO script 'unsafe-inline', anti-clickjacking,
 * nosniff, referrer + permissions policy). These lock the Phase 11 fixes so a later
 * change can't silently reopen them.
 */
return new class extends TestCase {
    private function request(array $server = []): Request
    {
        $req = new Request([], [], array_merge(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_HOST' => '127.0.0.1:8000'], $server), [], []);
        app()->instance('request', $req);

        return $req;
    }

    public function test_back_blocks_cross_origin_open_redirect(): void
    {
        $this->request(['HTTP_REFERER' => 'https://evil.example/phish']);
        $loc = (string) back()->getHeader('Location');
        $this->assertFalse(str_contains($loc, 'evil.example'));
        $this->assertSame(url('/'), $loc); // fell back to our own home
    }

    public function test_back_allows_same_origin_referer(): void
    {
        $same = rtrim(base_url(), '/') . '/dashboard';
        $this->request(['HTTP_REFERER' => $same]);
        $this->assertSame($same, (string) back()->getHeader('Location'));
    }

    public function test_back_allows_relative_referer(): void
    {
        $this->request(['HTTP_REFERER' => '/jobs']); // no host -> same origin
        $this->assertSame('/jobs', (string) back()->getHeader('Location'));
    }

    public function test_security_headers_applied(): void
    {
        $req = $this->request();
        $res = (new SecurityHeaders())->handle($req, static fn (Request $r): Response => Response::make('ok', 200));

        $this->assertSame('nosniff', $res->getHeader('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $res->getHeader('X-Frame-Options'));
        $this->assertNotNull($res->getHeader('Referrer-Policy'));
        $this->assertNotNull($res->getHeader('Permissions-Policy'));
    }

    public function test_csp_is_nonce_based_without_unsafe_inline_scripts(): void
    {
        $req = $this->request();
        $res = (new SecurityHeaders())->handle($req, static fn (Request $r): Response => Response::make('ok', 200));
        $csp = (string) $res->getHeader('Content-Security-Policy');

        // A fresh nonce is bound and present in the policy.
        $nonce = csp_nonce();
        $this->assertTrue($nonce !== '');
        $this->assertTrue(str_contains($csp, "script-src 'self' 'nonce-{$nonce}'"));

        // The script directive must NOT permit 'unsafe-inline' (the XSS hole).
        $this->assertFalse(str_contains($csp, "script-src 'self' 'unsafe-inline'"));

        // Lock-downs that prevent clickjacking, base-uri & object/plugin abuse.
        $this->assertTrue(str_contains($csp, "object-src 'none'"));
        $this->assertTrue(str_contains($csp, "frame-ancestors 'self'"));
        $this->assertTrue(str_contains($csp, "base-uri 'self'"));
        $this->assertTrue(str_contains($csp, "form-action 'self'"));
    }
};
