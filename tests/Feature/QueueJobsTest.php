<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Scheduling\CronRunner;
use App\Services\Scheduling\JobHandlers;
use App\Services\Scheduling\Queue;
use Tests\TestCase;

/**
 * Queue producer + real handlers (Phase 16). Proves the end-to-end loop now does
 * real work: a job pushed with Queue is drained by CronRunner and dispatched to its
 * registered handler. The built-in 'mail' handler sends via the Mailer (which logs
 * when disabled, so this runs offline with no network).
 */
return new class extends TestCase {
    protected bool $useDatabaseTransaction = true;

    public function test_mail_handler_is_registered_by_default(): void
    {
        $handlers = JobHandlers::default();
        $this->assertTrue($handlers->has('mail'));
        $this->assertTrue($handlers->has('noop'));
        $this->assertNotNull($handlers->resolve('mail'));
    }

    public function test_push_records_a_queued_job_with_the_typed_payload(): void
    {
        $id = (new Queue())->push('noop', ['k' => 'v'], null);
        $this->assertTrue($id > 0);

        $row = app('db')->table('queued_jobs')->where('id', '=', $id)->first();
        $this->assertNotNull($row);
        $this->assertTrue(str_contains((string) $row['payload'], '"type":"noop"'));
        $this->assertTrue(str_contains((string) $row['payload'], '"k":"v"'));
    }

    public function test_pushed_mail_job_is_drained_by_the_runner(): void
    {
        $id = (new Queue())->pushMail('queued@dest.test', 'Queued subject', '<p>queued body</p>', null);

        $summary = (new CronRunner())->run();

        // The job was processed (mail disabled in tests → Mailer logs + reports
        // success → handler returns normally → job done) and removed from the queue.
        $this->assertTrue($summary['jobs_processed'] >= 1);
        $this->assertFalse(app('db')->table('queued_jobs')->where('id', '=', $id)->exists());
    }

    public function test_a_job_with_no_handler_is_not_lost(): void
    {
        // An unknown type can't be dispatched; it must be retried/failed, never
        // silently dropped. After one run it is either still queued (retry) or in
        // failed_jobs — but never just gone.
        $id = (new Queue())->push('definitely-no-such-handler', [], null);

        (new CronRunner())->run();

        $stillQueued = app('db')->table('queued_jobs')->where('id', '=', $id)->exists();
        $failed = (int) app('db')->table('failed_jobs')->count() > 0;
        $this->assertTrue($stillQueued || $failed);
    }
};
