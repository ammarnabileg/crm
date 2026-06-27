<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Contracts\Mail\MailTransport;
use App\Contracts\Mail\SmtpSocket;
use Throwable;

/**
 * SMTP transport — a minimal, dependency-free SMTP client (no external libraries,
 * per the stack rules). It drives the standard dialogue over an injected SmtpSocket:
 * greeting → EHLO → (STARTTLS → EHLO) → (AUTH LOGIN) → MAIL FROM → RCPT TO → DATA →
 * message → QUIT. Encryption is 'ssl' (implicit, the socket opens ssl://), 'tls'
 * (STARTTLS upgrade) or 'none'. AUTH LOGIN runs only when a username is set.
 *
 * It NEVER throws: any unexpected reply, I/O error or exception ends the attempt
 * with a clean `false` so the Mailer falls back to logging — mail can never break a
 * request. Credentials are passed in (from config, itself fed by env or the
 * per-tenant settings overlay); none are stored or logged here.
 */
final class SmtpTransport implements MailTransport
{
    public function __construct(
        private readonly SmtpSocket $socket,
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
        private readonly string $encryption = 'tls',
        private readonly int $timeout = 15,
    ) {
    }

    public function send(string $to, string $subject, string $htmlBody, string $fromAddress, string $fromName): bool
    {
        try {
            if (! $this->socket->open($this->host, $this->port, $this->timeout)) {
                return false;
            }

            $ok = $this->run($to, $subject, $htmlBody, $fromAddress, $fromName);
            $this->socket->close();

            return $ok;
        } catch (Throwable) {
            $this->socket->close();

            return false;
        }
    }

    /** The full SMTP conversation. Returns false the moment any step is rejected. */
    private function run(string $to, string $subject, string $htmlBody, string $fromAddress, string $fromName): bool
    {
        if (! $this->expect(220)) {
            return false;
        }

        if (! $this->ehlo()) {
            return false;
        }

        if ($this->encryption === 'tls') {
            $this->socket->writeLine('STARTTLS');
            if (! $this->expect(220) || ! $this->socket->enableCrypto() || ! $this->ehlo()) {
                return false;
            }
        }

        if ($this->username !== '') {
            $this->socket->writeLine('AUTH LOGIN');
            if (! $this->expect(334)) {
                return false;
            }
            $this->socket->writeLine(base64_encode($this->username));
            if (! $this->expect(334)) {
                return false;
            }
            $this->socket->writeLine(base64_encode($this->password));
            if (! $this->expect(235)) {
                return false;
            }
        }

        $this->socket->writeLine('MAIL FROM:<' . $fromAddress . '>');
        if (! $this->expect(250)) {
            return false;
        }

        $this->socket->writeLine('RCPT TO:<' . $to . '>');
        if (! $this->expect(250, 251)) {
            return false;
        }

        $this->socket->writeLine('DATA');
        if (! $this->expect(354)) {
            return false;
        }

        // The message + the closing "." that ends DATA.
        $this->socket->writeLine($this->buildMessage($to, $subject, $htmlBody, $fromAddress, $fromName));
        $this->socket->writeLine('.');
        if (! $this->expect(250)) {
            return false;
        }

        $this->socket->writeLine('QUIT');
        $this->readResponse(); // best-effort 221; we already have our 250 for the message

        return true;
    }

    private function ehlo(): bool
    {
        $this->socket->writeLine('EHLO ' . $this->clientName());

        return $this->expect(250);
    }

    /** Read a (possibly multi-line) SMTP reply and check its leading status code. */
    private function expect(int ...$codes): bool
    {
        $code = $this->readResponse();

        return $code !== 0 && in_array($code, $codes, true);
    }

    /**
     * Read a full SMTP response. SMTP continuation lines look like "250-text" and the
     * final line "250 text" (a space in the 4th column). Returns the numeric status
     * code, or 0 on EOF/parse failure.
     */
    private function readResponse(): int
    {
        $code = 0;
        do {
            $line = $this->socket->readLine();
            if ($line === '') {
                return 0;
            }
            $code = (int) substr($line, 0, 3);
            $continues = isset($line[3]) && $line[3] === '-';
        } while ($continues);

        return $code;
    }

    /**
     * Build the RFC 5322 message: headers + CRLF + dot-stuffed HTML body. Lines use
     * CRLF; a leading "." on any body line is doubled so it can't end DATA early.
     */
    private function buildMessage(string $to, string $subject, string $htmlBody, string $fromAddress, string $fromName): string
    {
        $headers = [
            'Date: ' . date('r'),
            'From: ' . $this->encodeName($fromName) . ' <' . $fromAddress . '>',
            'To: <' . $to . '>',
            'Subject: ' . $this->encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $this->clientName() . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        $body = preg_replace('/^\./m', '..', $this->normalizeEol($htmlBody)) ?? $htmlBody;

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    /** Normalise any LF/CR-LF mix to CRLF for the wire. */
    private function normalizeEol(string $text): string
    {
        return str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], $text);
    }

    /** RFC 2047 encode a header value when it carries non-ASCII (e.g. Arabic). */
    private function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value) !== 1) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    /** Encode a display name, quoting it when needed and MIME-encoding non-ASCII. */
    private function encodeName(string $name): string
    {
        if (preg_match('/[^\x20-\x7E]/', $name) === 1) {
            return $this->encodeHeader($name);
        }

        return '"' . str_replace('"', '', $name) . '"';
    }

    private function clientName(): string
    {
        $host = (string) (parse_url('//' . $this->host, PHP_URL_HOST) ?: 'localhost');

        return $host !== '' ? $host : 'localhost';
    }
}
