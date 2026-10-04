<?php

declare(strict_types=1);

namespace HaHireAI\Core\Http\Session;

/**
 * A session storage backend. Implementations configure PHP's session subsystem
 * (save handler / path, or a registered SessionHandlerInterface) BEFORE
 * session_start() runs. This is the seam that lets storage move off the local
 * filesystem — a RedisSessionStore or DatabaseSessionStore only needs to
 * implement configure() and be registered on the factory; nothing else changes.
 */
interface SessionStore
{
    /**
     * Prepare PHP's session engine for this backend. Called once per request,
     * immediately before the session is started. MUST NOT start the session.
     */
    public function configure(): void;

    /** Stable driver name, e.g. 'file'. */
    public function name(): string;
}
