<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Contracts\Mail\SmtpSocket;

/**
 * In-memory SmtpSocket for tests: it replays a scripted list of server reply lines
 * and records every line the client writes, so the full SMTP dialogue can be
 * asserted OFFLINE (no real network). Mirrors the AI layer's FakeHttpClient.
 */
final class FakeSmtpSocket implements SmtpSocket
{
    /** @var string[] server reply lines, consumed in order by readLine() */
    private array $script;

    /** @var string[] every line the client wrote (assert against this) */
    public array $written = [];

    public bool $cryptoEnabled = false;
    public bool $opened = false;

    /**
     * @param string[] $script ordered server reply lines (e.g. '220 ready', '250-ok', '250 done')
     */
    public function __construct(array $script = [], private readonly bool $failOpen = false)
    {
        $this->script = $script;
    }

    public function open(string $host, int $port, int $timeout): bool
    {
        $this->opened = ! $this->failOpen;

        return $this->opened;
    }

    public function writeLine(string $line): void
    {
        $this->written[] = $line;
    }

    public function readLine(): string
    {
        return array_shift($this->script) ?? '';
    }

    public function enableCrypto(): bool
    {
        $this->cryptoEnabled = true;

        return true;
    }

    public function close(): void
    {
    }

    /** True if any written line contains the needle (test helper). */
    public function wrote(string $needle): bool
    {
        foreach ($this->written as $line) {
            if (str_contains($line, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** The full client transcript joined with CRLF (test helper). */
    public function transcript(): string
    {
        return implode("\r\n", $this->written);
    }
}
