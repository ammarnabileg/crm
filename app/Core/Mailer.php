<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal mailer. Uses PHP's mail() transport when the host has it configured;
 * otherwise it records the message to storage/logs/mail-*.log so flows like
 * password reset remain fully functional (and auditable) on hosts without SMTP.
 * A pluggable SMTP transport can be added later without touching callers.
 */
final class Mailer
{
    public function __construct(
        private readonly string $fromAddress,
        private readonly string $fromName,
        private readonly string $logPath,
    ) {
    }

    public function send(string $to, string $subject, string $htmlBody): bool
    {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->fromName . ' <' . $this->fromAddress . '>',
        ];

        $sent = false;
        if (function_exists('mail') && (bool) config('mail.enabled', false)) {
            $sent = @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
        }

        // Always log a copy (and rely on it when no transport is configured).
        $this->logMessage($to, $subject, $htmlBody, $sent);

        return $sent || ! (bool) config('mail.enabled', false);
    }

    private function logMessage(string $to, string $subject, string $body, bool $sent): void
    {
        if (! is_dir($this->logPath)) {
            @mkdir($this->logPath, 0775, true);
        }

        $entry = sprintf(
            "==== %s ====\nTo: %s\nSubject: %s\nDelivered: %s\n%s\n\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $sent ? 'yes (mail())' : 'logged only',
            $body
        );

        @file_put_contents($this->logPath . '/mail-' . date('Y-m-d') . '.log', $entry, FILE_APPEND | LOCK_EX);
    }
}
