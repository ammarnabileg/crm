<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Contracts\Mail\SmtpSocket;

/**
 * Production SmtpSocket over a real stream. Opens `tcp://` (plain / STARTTLS) or
 * `ssl://` (implicit TLS) depending on how it was constructed, then upgrades with
 * stream_socket_enable_crypto() when the transport issues STARTTLS. Every I/O call
 * is error-suppressed and degrades to a falsy result so the transport ends the
 * attempt cleanly (the Mailer then logs instead) — mail never breaks a request.
 */
final class StreamSmtpSocket implements SmtpSocket
{
    /** @var resource|null */
    private $stream = null;

    public function __construct(private readonly bool $implicitTls = false)
    {
    }

    public function open(string $host, int $port, int $timeout): bool
    {
        $timeout = max(1, $timeout);
        $scheme = $this->implicitTls ? 'ssl' : 'tcp';
        $errno = 0;
        $errstr = '';

        $stream = @stream_socket_client(
            "{$scheme}://{$host}:{$port}",
            $errno,
            $errstr,
            (float) $timeout,
            STREAM_CLIENT_CONNECT
        );

        if ($stream === false) {
            return false;
        }

        stream_set_timeout($stream, $timeout);
        $this->stream = $stream;

        return true;
    }

    public function writeLine(string $line): void
    {
        if ($this->stream !== null) {
            @fwrite($this->stream, $line . "\r\n");
        }
    }

    public function readLine(): string
    {
        if ($this->stream === null) {
            return '';
        }
        $line = @fgets($this->stream);

        return $line === false ? '' : rtrim($line, "\r\n");
    }

    public function enableCrypto(): bool
    {
        if ($this->stream === null) {
            return false;
        }

        return (bool) @stream_socket_enable_crypto(
            $this->stream,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
        );
    }

    public function close(): void
    {
        if ($this->stream !== null) {
            @fclose($this->stream);
            $this->stream = null;
        }
    }
}
