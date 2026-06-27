<?php

declare(strict_types=1);

namespace App\Contracts\Mail;

/**
 * The line-oriented socket seam SmtpTransport speaks SMTP over. Splitting it out
 * (mirroring the AI layer's HttpClient/FakeHttpClient seam) lets the whole SMTP
 * dialogue — EHLO/STARTTLS/AUTH/MAIL/RCPT/DATA — be tested OFFLINE against a scripted
 * fake, with the real stream socket used in production. Implementations never throw
 * on I/O; they signal failure via return values the transport checks.
 */
interface SmtpSocket
{
    /** Open the connection (tcp:// or ssl://). Returns false if it cannot connect. */
    public function open(string $host, int $port, int $timeout): bool;

    /** Write one command line; the implementation appends CRLF. */
    public function writeLine(string $line): void;

    /** Read one CRLF-terminated line (without the trailing CRLF). '' on EOF/error. */
    public function readLine(): string;

    /** Upgrade the open plaintext connection to TLS (STARTTLS). Returns success. */
    public function enableCrypto(): bool;

    /** Close the connection (best effort, never throws). */
    public function close(): void;
}
