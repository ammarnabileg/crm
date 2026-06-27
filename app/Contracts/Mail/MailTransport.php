<?php

declare(strict_types=1);

namespace App\Contracts\Mail;

/**
 * A mail delivery transport. The Mailer picks a transport at send time (SMTP when
 * configured, otherwise PHP mail()/log) so new transports plug in without touching
 * callers. A transport NEVER throws — a delivery failure is a `false` return so the
 * Mailer can fall back/log and the request is never broken by mail.
 */
interface MailTransport
{
    /**
     * Send one HTML message. Returns true on accepted delivery, false otherwise.
     */
    public function send(string $to, string $subject, string $htmlBody, string $fromAddress, string $fromName): bool;
}
