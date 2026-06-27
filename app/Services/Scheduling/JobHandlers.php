<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

/**
 * Registry that maps a queued-job "type" string to a callable handler.
 *
 * Every row in `queued_jobs` stores a JSON `payload` of the shape:
 *
 *     {"type": "<handler-key>", "data": { ... arbitrary handler input ... }}
 *
 * CronRunner decodes that payload, looks up the handler for its `type` here and
 * invokes it as `$handler(array $data, ?int $workspaceId): void`. A handler that
 * returns normally marks the job done; one that throws triggers the retry /
 * failed-jobs path in CronRunner. The handler must NOT assume a tenant context —
 * the queue runs as a system process, so the (nullable) workspace id is passed
 * explicitly and a handler that needs tenant scope should set it itself.
 *
 * This class is just the plumbing + extension seam: other modules call
 * register() during boot to attach the real work (send mail, build an export,
 * recompute a score, ...). The two built-ins below are intentionally tiny but
 * real, so the runner has something safe to resolve out of the box.
 */
final class JobHandlers
{
    /** @var array<string, callable> */
    private array $handlers = [];

    /**
     * Register (or override) the handler for a job type. Returns $this so
     * registrations can be chained.
     */
    public function register(string $type, callable $handler): self
    {
        $this->handlers[$type] = $handler;

        return $this;
    }

    /**
     * Resolve the handler for a job type, or null when none is registered.
     */
    public function resolve(string $type): ?callable
    {
        return $this->handlers[$type] ?? null;
    }

    /**
     * True when a handler is registered for the given type.
     */
    public function has(string $type): bool
    {
        return isset($this->handlers[$type]);
    }

    /**
     * A fresh registry pre-loaded with the built-in handlers. Modules layer
     * their own handlers on top of this via register().
     */
    public static function default(): self
    {
        $instance = new self();

        // 'noop' — does nothing, on purpose. Useful as a heartbeat job and as a
        // safe target in tests / smoke checks.
        $instance->register('noop', static function (array $data, ?int $workspaceId): void {
        });

        // 'log' — writes a line to storage/logs so an operator can confirm the
        // queue is draining end to end without wiring a real handler first.
        $instance->register('log', static function (array $data, ?int $workspaceId): void {
            logger()->info('queue.log', [
                'workspace_id' => $workspaceId,
                'data'         => $data,
            ]);
        });

        // 'mail' — a REAL handler: send an HTML email off the queue (so heavy or
        // flaky delivery is retryable rather than blocking a request). Producers use
        // Queue::pushMail(). A failed send throws so the runner retries / fails it;
        // when mail is disabled the Mailer logs and reports success (no retry).
        $instance->register('mail', static function (array $data, ?int $workspaceId): void {
            $to = (string) ($data['to'] ?? '');
            if ($to === '') {
                throw new \InvalidArgumentException('mail job is missing a "to" address.');
            }
            $sent = app('mailer')->send(
                $to,
                (string) ($data['subject'] ?? ''),
                (string) ($data['html'] ?? $data['body'] ?? '')
            );
            if (! $sent) {
                throw new \RuntimeException('mail delivery failed for ' . $to);
            }
        });

        return $instance;
    }
}
