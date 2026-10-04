<?php

declare(strict_types=1);

namespace HaHireAI\Tests\Unit\Core;

use HaHireAI\Core\Http\Session\SessionConfig;
use PHPUnit\Framework\TestCase;

/**
 * The session config VO is what makes the Secure flag "auto by default, override
 * on demand" — the exact behaviour that stops http/localhost from dropping the
 * login cookie while keeping HTTPS deployments secure.
 */
final class SessionConfigTest extends TestCase
{
    public function test_secure_auto_mirrors_the_request_scheme(): void
    {
        $config = SessionConfig::fromArray(['cookie' => ['secure' => 'auto']]);

        $this->assertTrue($config->resolveSecure(true), 'HTTPS request => Secure cookie');
        $this->assertFalse($config->resolveSecure(false), 'HTTP request => no Secure cookie');
    }

    public function test_secure_can_be_force_enabled_regardless_of_scheme(): void
    {
        foreach (['true', '1', 'on', 'yes', true] as $value) {
            $config = SessionConfig::fromArray(['cookie' => ['secure' => $value]]);
            $this->assertTrue($config->resolveSecure(false), 'forced-on stays on over http');
        }
    }

    public function test_secure_can_be_force_disabled_regardless_of_scheme(): void
    {
        foreach (['false', '0', 'off', 'no', false] as $value) {
            $config = SessionConfig::fromArray(['cookie' => ['secure' => $value]]);
            $this->assertFalse($config->resolveSecure(true), 'forced-off stays off over https');
        }
    }

    public function test_unknown_secure_value_defaults_to_auto(): void
    {
        $config = SessionConfig::fromArray(['cookie' => ['secure' => 'maybe']]);

        $this->assertSame(SessionConfig::SECURE_AUTO, $config->secureMode());
    }

    public function test_gc_maxlifetime_derives_from_lifetime_minutes(): void
    {
        $config = SessionConfig::fromArray(['lifetime' => 120]);

        $this->assertSame(7200, $config->gcMaxlifetime());
    }

    public function test_explicit_gc_maxlifetime_overrides_derivation(): void
    {
        $config = SessionConfig::fromArray(['lifetime' => 120, 'gc_maxlifetime' => 3600]);

        $this->assertSame(3600, $config->gcMaxlifetime());
    }

    public function test_cookie_lifetime_reflects_expire_on_close(): void
    {
        $persistent = SessionConfig::fromArray(['lifetime' => 60, 'expire_on_close' => false]);
        $ephemeral = SessionConfig::fromArray(['lifetime' => 60, 'expire_on_close' => true]);

        $this->assertSame(3600, $persistent->cookieLifetime());
        $this->assertSame(0, $ephemeral->cookieLifetime(), 'expire-on-close => browser-session cookie');
    }

    public function test_samesite_is_normalised_and_falls_back_to_lax(): void
    {
        $this->assertSame('Strict', SessionConfig::fromArray(['cookie' => ['same_site' => 'strict']])->sameSite());
        $this->assertSame('None', SessionConfig::fromArray(['cookie' => ['same_site' => 'none']])->sameSite());
        $this->assertSame('Lax', SessionConfig::fromArray(['cookie' => ['same_site' => 'bogus']])->sameSite());
    }

    public function test_defaults_are_production_sane(): void
    {
        $config = SessionConfig::fromArray([]);

        $this->assertSame('file', $config->driver());
        $this->assertSame('hahireai_session', $config->cookieName());
        $this->assertSame('/', $config->cookiePath());
        $this->assertSame('', $config->cookieDomain());
        $this->assertTrue($config->httpOnly());
        $this->assertFalse($config->trustProxy());
        $this->assertSame(SessionConfig::SECURE_AUTO, $config->secureMode());
    }

    public function test_zero_or_negative_lifetime_is_clamped(): void
    {
        $this->assertSame(1, SessionConfig::fromArray(['lifetime' => 0])->lifetimeMinutes());
        $this->assertSame(1, SessionConfig::fromArray(['lifetime' => -5])->lifetimeMinutes());
    }
}
