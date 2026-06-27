<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Mail\MailTransport;
use App\Core\Mailer;
use App\Infrastructure\Mail\FakeSmtpSocket;
use App\Infrastructure\Mail\SmtpTransport;
use Tests\TestCase;

/**
 * SMTP transport (Phase 16 — Email). The whole SMTP dialogue runs OFFLINE through a
 * scripted FakeSmtpSocket (mirroring the AI layer's FakeHttpClient), so EHLO/STARTTLS/
 * AUTH/MAIL/RCPT/DATA are verified with no network. Also covers the Mailer's transport
 * selection + graceful degradation: SMTP credentials are OPTIONAL — with mail disabled
 * the Mailer logs only and still reports success, never touching a transport.
 */
return new class extends TestCase {
    /** Reset the mail config this test mutates so it can't leak into other tests. */
    public function tearDown(): void
    {
        config()->set('mail.enabled', false);
        config()->set('mail.smtp.host', '');
    }

    private function transport(FakeSmtpSocket $socket, string $encryption = 'tls', string $user = 'mailer', string $pass = 'secret'): SmtpTransport
    {
        return new SmtpTransport($socket, 'smtp.test', 587, $user, $pass, $encryption, 10);
    }

    public function test_successful_send_runs_the_full_dialogue_with_starttls_and_auth(): void
    {
        $socket = new FakeSmtpSocket([
            '220 smtp.test ESMTP ready',
            '250-smtp.test', '250-STARTTLS', '250 AUTH LOGIN', // EHLO #1
            '220 ready to start TLS',                          // STARTTLS
            '250-smtp.test', '250 AUTH LOGIN',                 // EHLO #2 (post-TLS)
            '334 VXNlcm5hbWU6',                                // AUTH LOGIN -> username prompt
            '334 UGFzc3dvcmQ6',                                // -> password prompt
            '235 2.7.0 Authentication succeeded',
            '250 2.1.0 OK',                                    // MAIL FROM
            '250 2.1.5 OK',                                    // RCPT TO
            '354 End data with <CR><LF>.<CR><LF>',             // DATA
            '250 2.0.0 OK: queued',                            // message body + "."
            '221 2.0.0 Bye',                                   // QUIT
        ]);

        $ok = $this->transport($socket)->send('to@dest.test', 'Hello', '<p>Hi</p>', 'from@app.test', 'App Sender');

        $this->assertTrue($ok);
        $this->assertTrue($socket->cryptoEnabled, 'STARTTLS should upgrade the connection');
        $this->assertTrue($socket->wrote('EHLO smtp.test'));
        $this->assertTrue($socket->wrote('STARTTLS'));
        $this->assertTrue($socket->wrote('AUTH LOGIN'));
        $this->assertTrue($socket->wrote(base64_encode('mailer')));
        $this->assertTrue($socket->wrote(base64_encode('secret')));
        $this->assertTrue($socket->wrote('MAIL FROM:<from@app.test>'));
        $this->assertTrue($socket->wrote('RCPT TO:<to@dest.test>'));
        $this->assertTrue($socket->wrote('DATA'));
        $this->assertTrue($socket->wrote('Subject: Hello'));
        $this->assertTrue($socket->wrote('<p>Hi</p>'));
        $this->assertTrue(in_array('.', $socket->written, true), 'DATA must be terminated by a lone "."');
        $this->assertTrue($socket->wrote('QUIT'));
    }

    public function test_auth_failure_returns_false(): void
    {
        $socket = new FakeSmtpSocket([
            '220 smtp.test ESMTP ready',
            '250 AUTH LOGIN',          // EHLO (no STARTTLS offered; we use 'none')
            '334 VXNlcm5hbWU6',
            '334 UGFzc3dvcmQ6',
            '535 5.7.8 Authentication credentials invalid',
        ]);

        $ok = $this->transport($socket, encryption: 'none')->send('to@dest.test', 'S', '<p>x</p>', 'from@app.test', 'App');

        $this->assertFalse($ok);
    }

    public function test_open_failure_returns_false(): void
    {
        $socket = new FakeSmtpSocket([], failOpen: true);
        $ok = $this->transport($socket)->send('to@dest.test', 'S', '<p>x</p>', 'from@app.test', 'App');
        $this->assertFalse($ok);
    }

    public function test_rejected_recipient_returns_false(): void
    {
        $socket = new FakeSmtpSocket([
            '220 smtp.test ESMTP ready',
            '250 smtp.test',           // EHLO
            '250 OK',                  // MAIL FROM
            '550 5.1.1 No such user',  // RCPT TO rejected
        ]);

        $ok = $this->transport($socket, encryption: 'none', user: '', pass: '')
            ->send('nobody@dest.test', 'S', '<p>x</p>', 'from@app.test', 'App');

        $this->assertFalse($ok);
    }

    public function test_no_auth_when_username_is_empty(): void
    {
        $socket = new FakeSmtpSocket([
            '220 smtp.test ESMTP ready',
            '250 smtp.test',  // EHLO
            '250 OK',         // MAIL FROM
            '250 OK',         // RCPT TO
            '354 go',         // DATA
            '250 queued',     // body + .
            '221 bye',        // QUIT
        ]);

        $ok = $this->transport($socket, encryption: 'none', user: '', pass: '')
            ->send('to@dest.test', 'S', '<p>x</p>', 'from@app.test', 'App');

        $this->assertTrue($ok);
        $this->assertFalse($socket->wrote('AUTH LOGIN'), 'No AUTH when there are no credentials');
    }

    public function test_body_is_dot_stuffed_and_unicode_subject_is_encoded(): void
    {
        $socket = new FakeSmtpSocket([
            '220 smtp.test ESMTP ready',
            '250 smtp.test', // EHLO
            '250 OK',        // MAIL FROM
            '250 OK',        // RCPT TO
            '354 go',        // DATA
            '250 queued',    // body + .
            '221 bye',       // QUIT
        ]);

        // Body has a line that begins with "." (must be doubled so it can't end DATA).
        $body = "Line one\n.hidden command\nLine three";
        $ok = $this->transport($socket, encryption: 'none', user: '', pass: '')
            ->send('to@dest.test', 'مرحبا بالعالم', $body, 'from@app.test', 'App');

        $this->assertTrue($ok);
        $transcript = $socket->transcript();
        $this->assertTrue(str_contains($transcript, "\r\n..hidden command"), 'leading-dot line must be dot-stuffed');
        // The Arabic subject is RFC 2047 encoded-word, not raw.
        $this->assertTrue(str_contains($transcript, '=?UTF-8?B?'));
        $this->assertFalse(str_contains($transcript, 'Subject: مرحبا'));
    }

    public function test_mailer_uses_injected_transport_when_enabled(): void
    {
        config()->set('mail.enabled', true);

        $spy = new class implements MailTransport {
            public array $calls = [];
            public function send(string $to, string $subject, string $htmlBody, string $fromAddress, string $fromName): bool
            {
                $this->calls[] = compact('to', 'subject', 'fromAddress');

                return true;
            }
        };

        $mailer = new Mailer('from@app.test', 'App', storage_path('logs'), $spy);
        $ok = $mailer->send('to@dest.test', 'Welcome', '<p>hi</p>');

        $this->assertTrue($ok);
        $this->assertCount(1, $spy->calls);
        $this->assertSame('to@dest.test', $spy->calls[0]['to']);
    }

    public function test_mailer_logs_only_and_skips_transport_when_disabled(): void
    {
        config()->set('mail.enabled', false);

        $spy = new class implements MailTransport {
            public bool $called = false;
            public function send(string $to, string $subject, string $htmlBody, string $fromAddress, string $fromName): bool
            {
                $this->called = true;

                return false;
            }
        };

        $mailer = new Mailer('from@app.test', 'App', storage_path('logs'), $spy);
        // Disabled mail is intentionally log-only and reports success...
        $this->assertTrue($mailer->send('to@dest.test', 'S', '<p>x</p>'));
        // ...without ever touching the transport.
        $this->assertFalse($spy->called);
    }
};
