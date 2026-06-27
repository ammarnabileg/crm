<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Core\Model;

/**
 * Enqueue side — the tiny producer that puts work on `queued_jobs` for the
 * CronRunner to drain. A job is a JSON payload of the shape
 * {"type": "<handler-key>", "data": {...}} matched to a handler in JobHandlers.
 *
 * Timestamps are UNIX ints (the column type); `available_at` lets a job be delayed.
 * The runner reserves, dispatches, and on failure retries with backoff or moves the
 * job to `failed_jobs` — so producers just push and forget.
 */
final class Queue
{
    /**
     * Push a job onto the queue and return its id.
     *
     * @param array<string,mixed> $data handler input
     */
    public function push(string $type, array $data = [], ?int $workspaceId = null, int $delaySeconds = 0): int
    {
        $now = time();

        return (int) app('db')->table('queued_jobs')->insertGetId([
            'uuid'         => Model::generateUuid(),
            'workspace_id' => $workspaceId,
            'queue'        => 'default',
            'payload'      => json_encode(['type' => $type, 'data' => $data], JSON_UNESCAPED_SLASHES) ?: '{"type":"noop","data":{}}',
            'attempts'     => 0,
            'reserved_at'  => null,
            'available_at' => $now + max(0, $delaySeconds),
            'created_at'   => $now,
        ]);
    }

    /** Convenience producer for the built-in 'mail' handler (queued, retryable email). */
    public function pushMail(string $to, string $subject, string $htmlBody, ?int $workspaceId = null): int
    {
        return $this->push('mail', ['to' => $to, 'subject' => $subject, 'html' => $htmlBody], $workspaceId);
    }
}
