<?php

declare(strict_types=1);

namespace HaHireAI\Core\Http;

use HaHireAI\Core\Http\Session\SessionConfig;
use HaHireAI\Core\Http\Session\SessionStoreFactory;

/**
 * Thin wrapper over native PHP sessions with CSRF + flash support.
 *
 * Production-ready configuration lives in config/session.php: storage is
 * driver-based (file now; redis/database registrable via SessionStoreFactory),
 * lifetime/GC/cookie flags all come from config, and the Secure cookie flag is
 * auto-detected from the request scheme (reverse-proxy / Cloudflare aware)
 * unless explicitly overridden. Cookies are HttpOnly + SameSite by default
 * (docs/SECURITY_GUIDE.md).
 *
 * Backward compatibility: all accessors keep their original signatures and
 * start() still works with no arguments (falls back to $_SERVER-based scheme
 * detection when no Request is supplied).
 */
final class Session
{
    private readonly SessionConfig $config;

    private readonly SessionStoreFactory $stores;

    /**
     * Both dependencies are optional so `new Session()` keeps working for the
     * accessor-only use (get/put/flash/csrf in tests) exactly as before; the
     * container always injects the real config-backed instance.
     */
    public function __construct(?SessionConfig $config = null, ?SessionStoreFactory $stores = null)
    {
        $this->config = $config ?? SessionConfig::fromArray([]);
        $this->stores = $stores ?? new SessionStoreFactory();
    }

    public function start(?Request $request = null): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
            return;
        }

        // Configure the storage backend (save_handler/path) before starting.
        $this->stores->make($this->config)->configure();

        ini_set('session.gc_maxlifetime', (string) $this->config->gcMaxlifetime());
        ini_set('session.use_strict_mode', '1'); // reject uninitialised session ids (fixation defence)

        session_name($this->config->cookieName());
        session_set_cookie_params([
            'lifetime' => $this->config->cookieLifetime(),
            'path' => $this->config->cookiePath(),
            'domain' => $this->config->cookieDomain(),
            'secure' => $this->config->resolveSecure($this->requestIsSecure($request)),
            'httponly' => $this->config->httpOnly(),
            'samesite' => $this->config->sameSite(),
        ]);

        session_start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function flush(): void
    {
        $_SESSION = [];
    }

    /** Flash a value for the next request only. */
    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public function pullFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);

        return $value;
    }

    public function csrfToken(): string
    {
        if (! isset($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public function verifyCsrf(?string $token): bool
    {
        return is_string($token) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
    }

    /** Scheme resolution: prefer the captured Request, fall back to raw $_SERVER. */
    private function requestIsSecure(?Request $request): bool
    {
        if ($request !== null) {
            return $request->isSecure($this->config->trustProxy());
        }

        $https = $_SERVER['HTTPS'] ?? '';

        return (is_string($https) && $https !== '' && strtolower($https) !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }
}
