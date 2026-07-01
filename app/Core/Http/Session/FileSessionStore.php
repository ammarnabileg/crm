<?php

declare(strict_types=1);

namespace HaHireAI\Core\Http\Session;

use RuntimeException;

/**
 * File-backed sessions written to an app-owned directory (storage/sessions),
 * created automatically on first boot. Deliberately NOT PHP's default
 * /var/lib/php/sessions: that path is shared across apps and swept by the
 * distro's session-gc cron, which is a classic cause of "randomly logged out".
 */
final class FileSessionStore implements SessionStore
{
    public function __construct(private readonly string $path)
    {
    }

    public function configure(): void
    {
        if ($this->path === '') {
            throw new RuntimeException('File session store requires a non-empty path (config session.files).');
        }

        if (! is_dir($this->path) && ! @mkdir($this->path, 0770, true) && ! is_dir($this->path)) {
            throw new RuntimeException("Session directory is not writable and could not be created: {$this->path}");
        }

        // Harden the directory: private to the app user, never web-served.
        @chmod($this->path, 0770);
        $this->protect();

        ini_set('session.save_handler', 'files');
        session_save_path($this->path);
    }

    public function name(): string
    {
        return 'file';
    }

    /** Drop a deny-all .htaccess so the session files are never reachable over HTTP. */
    private function protect(): void
    {
        $guard = $this->path . '/.htaccess';

        if (! is_file($guard)) {
            @file_put_contents($guard, "Require all denied\nDeny from all\n");
        }
    }
}
