<?php

declare(strict_types=1);

namespace App\Core;

use App\Contracts\Mail\MailTransport;
use App\Infrastructure\Mail\SmtpTransport;
use App\Infrastructure\Mail\StreamSmtpSocket;

/**
 * Mailer. Picks a transport at send time:
 *   - SMTP (App\Infrastructure\Mail\SmtpTransport) when an SMTP host is configured
 *     (config mail.smtp.host — fed by env or the per-tenant Settings → Email overlay);
 *   - else PHP mail() when the host has it and mail is enabled;
 *   - and ALWAYS records a copy to storage/logs/mail-*.log, which is the sole
 *     delivery path when nothing is configured.
 *
 * SMTP credentials are optional: with no host the mailer degrades to mail()/log, so
 * flows like password reset stay fully functional with zero configuration. A transport
 * never throws — a failure is logged and reported as false; mail can't break a request.
 */
final class Mailer
{
    public function __construct(
        private readonly string $fromAddress,
        private readonly string $fromName,
        private readonly string $logPath,
        private readonly ?MailTransport $transport = null,
    ) {
    }

    public function send(string $to, string $subject, string $htmlBody): bool
    {
        $enabled = (bool) config('mail.enabled', false);
        $transport = $this->transport ?? $this->resolveSmtpTransport();

        $sent = false;
        $via = 'logged only';

        if ($enabled && $transport !== null) {
            $sent = $transport->send($to, $subject, $htmlBody, $this->fromAddress, $this->fromName);
            $via = $sent ? 'yes (smtp)' : 'smtp failed — logged';
        } elseif ($enabled && function_exists('mail')) {
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $this->fromName . ' <' . $this->fromAddress . '>',
            ];
            $sent = @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
            $via = $sent ? 'yes (mail())' : 'mail() failed — logged';
        }

        // Always log a copy (and rely on it when no transport is configured).
        $this->logMessage($to, $subject, $htmlBody, $via);

        // Disabled mail is "successful" (intentionally log-only); enabled mail must
        // have actually been accepted by a transport.
        return $enabled ? $sent : true;
    }

    /**
     * Build an SMTP transport from config when a host is set, else null (→ mail()/log).
     * Read lazily at send time so the per-tenant mail overlay (Application boot) and
     * env both apply without rebuilding the Mailer singleton.
     */
    private function resolveSmtpTransport(): ?MailTransport
    {
        $host = (string) config('mail.smtp.host', '');
        if ($host === '') {
            return null;
        }

        $encryption = strtolower((string) config('mail.smtp.encryption', 'tls'));

        return new SmtpTransport(
            new StreamSmtpSocket(implicitTls: $encryption === 'ssl'),
            $host,
            (int) config('mail.smtp.port', 587),
            (string) config('mail.smtp.username', ''),
            (string) config('mail.smtp.password', ''),
            $encryption,
            (int) config('mail.smtp.timeout', 15),
        );
    }

    private function logMessage(string $to, string $subject, string $body, string $delivered): void
    {
        if (! is_dir($this->logPath)) {
            @mkdir($this->logPath, 0775, true);
        }

        $entry = sprintf(
            "==== %s ====\nTo: %s\nSubject: %s\nDelivered: %s\n%s\n\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $delivered,
            $body
        );

        @file_put_contents($this->logPath . '/mail-' . date('Y-m-d') . '.log', $entry, FILE_APPEND | LOCK_EX);
    }
}
