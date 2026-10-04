<?php

declare(strict_types=1);

namespace HaHireAI\Core\Http\Session;

/**
 * Immutable, typed view over config/session.php. Normalises the loosely-typed
 * environment values (strings, booleans, "auto") into the exact shapes the
 * Session wrapper and storage drivers need — so no parsing leaks into runtime.
 */
final class SessionConfig
{
    public const SECURE_ON = 'on';
    public const SECURE_OFF = 'off';
    public const SECURE_AUTO = 'auto';

    private function __construct(
        private readonly string $driver,
        private readonly string $filePath,
        private readonly int $lifetimeMinutes,
        private readonly int $gcMaxlifetime,
        private readonly bool $expireOnClose,
        private readonly bool $trustProxy,
        private readonly string $cookieName,
        private readonly string $cookiePath,
        private readonly string $cookieDomain,
        private readonly string $secureMode,
        private readonly bool $httpOnly,
        private readonly string $sameSite,
    ) {
    }

    /** @param array<string, mixed> $config the `session` config array */
    public static function fromArray(array $config): self
    {
        $cookie = is_array($config['cookie'] ?? null) ? $config['cookie'] : [];

        $lifetime = max(1, (int) ($config['lifetime'] ?? 120));
        $gcRaw = $config['gc_maxlifetime'] ?? null;
        $gc = ($gcRaw === null || $gcRaw === '') ? $lifetime * 60 : max(60, (int) $gcRaw);

        $filePath = (string) ($config['files'] ?? '');

        return new self(
            driver: strtolower(trim((string) ($config['driver'] ?? 'file'))) ?: 'file',
            // Fall back to an app-namespaced temp dir (never PHP's shared system
            // path) so a bare, unconfigured Session is still usable and isolated.
            filePath: $filePath !== '' ? $filePath : sys_get_temp_dir() . '/hahireai_sessions',
            lifetimeMinutes: $lifetime,
            gcMaxlifetime: $gc,
            expireOnClose: (bool) ($config['expire_on_close'] ?? false),
            trustProxy: (bool) ($config['trust_proxy'] ?? false),
            cookieName: (string) ($cookie['name'] ?? 'hahireai_session') ?: 'hahireai_session',
            cookiePath: (string) ($cookie['path'] ?? '/') ?: '/',
            cookieDomain: (string) ($cookie['domain'] ?? ''),
            secureMode: self::normaliseSecure($cookie['secure'] ?? self::SECURE_AUTO),
            httpOnly: (bool) ($cookie['http_only'] ?? true),
            sameSite: self::normaliseSameSite($cookie['same_site'] ?? 'Lax'),
        );
    }

    /** true|false force the flag; anything else ("auto"/null/"") means auto-detect. */
    private static function normaliseSecure(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? self::SECURE_ON : self::SECURE_OFF;
        }

        $token = strtolower(trim((string) $value));

        return match ($token) {
            'true', '1', 'on', 'yes' => self::SECURE_ON,
            'false', '0', 'off', 'no' => self::SECURE_OFF,
            default => self::SECURE_AUTO,
        };
    }

    private static function normaliseSameSite(mixed $value): string
    {
        $token = ucfirst(strtolower(trim((string) $value)));

        return in_array($token, ['Lax', 'Strict', 'None'], true) ? $token : 'Lax';
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function filePath(): string
    {
        return $this->filePath;
    }

    public function lifetimeMinutes(): int
    {
        return $this->lifetimeMinutes;
    }

    public function gcMaxlifetime(): int
    {
        return $this->gcMaxlifetime;
    }

    /** Cookie max-age in seconds: 0 (browser session) when expire-on-close. */
    public function cookieLifetime(): int
    {
        return $this->expireOnClose ? 0 : $this->lifetimeMinutes * 60;
    }

    public function trustProxy(): bool
    {
        return $this->trustProxy;
    }

    public function cookieName(): string
    {
        return $this->cookieName;
    }

    public function cookiePath(): string
    {
        return $this->cookiePath;
    }

    public function cookieDomain(): string
    {
        return $this->cookieDomain;
    }

    public function secureMode(): string
    {
        return $this->secureMode;
    }

    public function httpOnly(): bool
    {
        return $this->httpOnly;
    }

    public function sameSite(): string
    {
        return $this->sameSite;
    }

    /**
     * Resolve the Secure cookie flag. Forced modes win; otherwise it mirrors the
     * request scheme (auto) — this is what stops http/localhost from silently
     * dropping the cookie while keeping HTTPS deployments secure.
     */
    public function resolveSecure(bool $requestIsSecure): bool
    {
        return match ($this->secureMode) {
            self::SECURE_ON => true,
            self::SECURE_OFF => false,
            default => $requestIsSecure,
        };
    }
}
