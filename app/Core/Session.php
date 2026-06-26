<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session manager with flash data + old-input support.
 *
 * Uses PHP's native session handler but with hardened cookie parameters and a
 * dedicated file store under storage/sessions so multiple apps on shared
 * hosting do not collide.
 */
final class Session
{
    private bool $started = false;

    public function __construct(
        private readonly string $savePath,
        private readonly string $cookieName = 'halaops_session',
        private readonly bool $secure = false,
        private readonly int $lifetime = 7200,
    ) {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            $this->ageFlash();
            return;
        }

        if (! is_dir($this->savePath)) {
            @mkdir($this->savePath, 0775, true);
        }

        if (is_dir($this->savePath) && is_writable($this->savePath)) {
            session_save_path($this->savePath);
        }

        session_name($this->cookieName);
        session_set_cookie_params([
            'lifetime' => $this->lifetime,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
        $this->started = true;
        $this->ageFlash();
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

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function all(): array
    {
        return $_SESSION ?? [];
    }

    public function flash(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
        $_SESSION['_flash']['new'][] = $key;
    }

    public function flashInput(array $input): void
    {
        $this->flash('_old_input', $input);
    }

    public function old(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_old_input'][$key] ?? $default;
    }

    public function regenerate(): void
    {
        if ($this->started) {
            session_regenerate_id(true);
        }
    }

    public function invalidate(): void
    {
        $_SESSION = [];
        if ($this->started) {
            session_regenerate_id(true);
        }
    }

    public function token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    /**
     * Promote "new" flash keys to "old" so they survive exactly one request,
     * then clear the previous request's flash payload.
     */
    private function ageFlash(): void
    {
        $old = $_SESSION['_flash']['old'] ?? [];
        foreach ($old as $key) {
            unset($_SESSION[$key]);
        }

        $_SESSION['_flash']['old'] = $_SESSION['_flash']['new'] ?? [];
        $_SESSION['_flash']['new'] = [];
    }
}
