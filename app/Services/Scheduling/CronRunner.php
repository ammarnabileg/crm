<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use Throwable;

/**
 * No-terminal CRON / queue runner.
 *
 * One call to run() does two things a production box would normally need SSH for:
 *
 *  1. Ticks the scheduler: each active `scheduled_tasks` row that is due
 *     (next_run_at IS NULL OR next_run_at <= now) is stamped with last_run_at,
 *     last_status='ran' and a freshly computed next_run_at. This is a heartbeat +
 *     due-marker only — it does NOT execute scheduled_tasks.command (no shell is
 *     ever spawned). The diagnostics page reads last_run_at/next_run_at to prove
 *     the cron is alive.
 *
 *  2. Drains the queue: up to $maxJobs due `queued_jobs` rows
 *     (available_at <= now AND reserved_at IS NULL) are reserved, decoded, and
 *     dispatched to the handler registered for their payload "type". Success
 *     deletes the row; an exception bumps attempts and either retries with
 *     backoff or, at the attempt ceiling, moves the row to `failed_jobs`. Every
 *     job is wrapped in its own try/catch so one poison job never aborts the
 *     batch, and the whole thing is a safe no-op when there is nothing due.
 *
 * It is deliberately dependency-light (talks to app('db') directly) and runs as a
 * SYSTEM process: jobs may carry a nullable workspace_id, but no tenant context
 * is required or assumed.
 *
 * NEXT-RUN LIMITATION (documented, honest): a full 5-field cron parser is out of
 * scope. computeNextRun() understands only a few common shapes — '@hourly',
 * '@daily'/'@midnight', '@weekly', '@monthly', the all-stars '* * * * *' (treated
 * as "run next tick", +1 min) and a leading 'minute' step '*\/N * * * *'. Anything
 * else falls back to a +1 hour heartbeat so the task keeps re-firing on a sane
 * cadence rather than silently stalling. Schedules needing exact field semantics
 * should encode the cadence in their handler, not rely on this fallback.
 */
final class CronRunner
{
    /** Attempts at/after which a failing job is moved to failed_jobs instead of retried. */
    private const MAX_ATTEMPTS = 3;

    private JobHandlers $handlers;

    public function __construct(?JobHandlers $handlers = null)
    {
        $this->handlers = $handlers ?? JobHandlers::default();
    }

    /**
     * Tick the scheduler then drain due jobs.
     *
     * @return array{scheduled_ticked:int, jobs_processed:int, jobs_failed:int, started_at:string, finished_at:string}
     */
    public function run(int $maxJobs = 100): array
    {
        $startedAt = now();

        $scheduledTicked = $this->tickScheduler();
        [$processed, $failed] = $this->drainQueue(max(0, $maxJobs));

        return [
            'scheduled_ticked' => $scheduledTicked,
            'jobs_processed'   => $processed,
            'jobs_failed'      => $failed,
            'started_at'       => $startedAt,
            'finished_at'      => now(),
        ];
    }

    /**
     * Mark every due, active scheduled task as run and advance its next_run_at.
     * Returns the number of tasks ticked.
     */
    private function tickScheduler(): int
    {
        $db = app('db');
        $now = now();

        $due = $db->table('scheduled_tasks')
            ->where('is_active', '=', 1)
            ->whereRaw('(next_run_at IS NULL OR next_run_at <= ?)', [$now])
            ->get();

        $ticked = 0;
        foreach ($due as $task) {
            try {
                $db->table('scheduled_tasks')
                    ->where('id', '=', (int) $task['id'])
                    ->update([
                        'last_run_at' => $now,
                        'last_status' => 'ran',
                        'next_run_at' => $this->computeNextRun((string) ($task['cron'] ?? ''), $now),
                        'updated_at'  => $now,
                    ]);
                $ticked++;
            } catch (Throwable) {
                // A single malformed task row must not abort the tick.
            }
        }

        return $ticked;
    }

    /**
     * Reserve and process up to $maxJobs due jobs.
     *
     * @return array{0:int,1:int} [processed, failed]
     */
    private function drainQueue(int $maxJobs): array
    {
        if ($maxJobs === 0) {
            return [0, 0];
        }

        $db = app('db');
        $now = time();

        $jobs = $db->table('queued_jobs')
            ->whereNull('reserved_at')
            ->where('available_at', '<=', $now)
            ->orderBy('available_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit($maxJobs)
            ->get();

        $processed = 0;
        $failed = 0;

        foreach ($jobs as $job) {
            $id = (int) $job['id'];

            // Reserve atomically: only claim the row if it is still unreserved,
            // so a concurrent runner cannot grab the same job.
            $claimed = $db->table('queued_jobs')
                ->where('id', '=', $id)
                ->whereNull('reserved_at')
                ->update(['reserved_at' => time()]);
            if ($claimed < 1) {
                continue;
            }

            try {
                $this->dispatch($job);
                $db->table('queued_jobs')->where('id', '=', $id)->delete();
                $processed++;
            } catch (Throwable $e) {
                if ($this->handleFailure($job, $e)) {
                    $failed++;
                }
            }
        }

        return [$processed, $failed];
    }

    /**
     * Decode the payload and invoke the resolved handler. Throws when the
     * payload is unusable or no handler is registered, which routes the job to
     * the retry / failed path.
     *
     * @param array<string, mixed> $job
     */
    private function dispatch(array $job): void
    {
        $payload = json_decode((string) ($job['payload'] ?? ''), true);
        if (! is_array($payload)) {
            throw new \RuntimeException('Job payload is not valid JSON.');
        }

        $type = (string) ($payload['type'] ?? '');
        if ($type === '') {
            throw new \RuntimeException('Job payload is missing a "type".');
        }

        $handler = $this->handlers->resolve($type);
        if ($handler === null) {
            throw new \RuntimeException("No handler registered for job type [{$type}].");
        }

        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        $workspaceId = isset($job['workspace_id']) ? (int) $job['workspace_id'] : null;

        $handler($data, $workspaceId);
    }

    /**
     * A job threw: bump attempts and either retry with backoff or move it to
     * failed_jobs once the attempt ceiling is reached. Returns true only when
     * the job was moved to failed_jobs (so the caller can count it).
     *
     * @param array<string, mixed> $job
     */
    private function handleFailure(array $job, Throwable $e): bool
    {
        $db = app('db');
        $id = (int) $job['id'];
        $attempts = (int) ($job['attempts'] ?? 0) + 1;

        if ($attempts >= self::MAX_ATTEMPTS) {
            try {
                $db->table('failed_jobs')->insert([
                    'uuid'         => \App\Core\Model::generateUuid(),
                    'workspace_id' => $job['workspace_id'] ?? null,
                    'queue'        => (string) ($job['queue'] ?? 'default'),
                    'payload'      => (string) ($job['payload'] ?? ''),
                    'exception'    => $this->formatException($e),
                    'failed_at'    => now(),
                ]);
                $db->table('queued_jobs')->where('id', '=', $id)->delete();

                return true;
            } catch (Throwable) {
                // If the move fails, fall through to release so the job is not lost.
            }
        }

        // Release for a later attempt with a simple linear backoff, clearing the
        // reservation so the next run can pick it up.
        $db->table('queued_jobs')
            ->where('id', '=', $id)
            ->update([
                'attempts'    => $attempts,
                'reserved_at' => null,
                'available_at' => time() + $this->backoffSeconds($attempts),
            ]);

        return false;
    }

    /**
     * Seconds to wait before the next attempt (linear: 60s, 120s, ...).
     */
    private function backoffSeconds(int $attempts): int
    {
        return 60 * max(1, $attempts);
    }

    /**
     * Compact, storable representation of a throwable for failed_jobs.exception.
     */
    private function formatException(Throwable $e): string
    {
        return $e::class . ': ' . $e->getMessage() . ' @ '
            . $e->getFile() . ':' . $e->getLine() . PHP_EOL
            . $e->getTraceAsString();
    }

    /**
     * Best-effort next-run time for a cron expression. See the class docblock for
     * the (deliberately limited) set of expressions understood; everything else
     * falls back to a +1 hour heartbeat.
     */
    private function computeNextRun(string $cron, string $now): string
    {
        $base = strtotime($now) ?: time();
        $expr = trim($cron);

        $next = match (strtolower($expr)) {
            '@hourly'             => $base + 3600,
            '@daily', '@midnight' => strtotime('tomorrow', $base) ?: $base + 86400,
            '@weekly'             => $base + 7 * 86400,
            '@monthly'            => strtotime('+1 month', $base) ?: $base + 30 * 86400,
            '* * * * *'           => $base + 60,
            default               => null,
        };

        if ($next === null) {
            // Leading minute step like "*/N * * * *" (other fields all stars).
            if (preg_match('#^\*/(\d+)\s+\*\s+\*\s+\*\s+\*$#', $expr, $m)) {
                $step = max(1, (int) $m[1]);
                $next = $base + $step * 60;
            }
        }

        // Documented fallback: keep the task firing roughly hourly.
        $next ??= $base + 3600;

        return date('Y-m-d H:i:s', $next);
    }
}
