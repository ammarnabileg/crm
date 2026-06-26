<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Contracts\Audit\AuditLogger;
use App\Core\Database;
use App\Services\Auth\AuthManager;
use App\Services\Tenancy\TenantManager;

/**
 * Writes the audit trail to the `activity_log` table. Captures the actor (from
 * auth or context), tenant, IP, user-agent, and a coarse device classification;
 * change records also store old/new value JSON. Inserts are unscoped (the log is
 * written for whatever workspace the action targeted), mirroring docs/38.
 */
final class DatabaseAuditLogger implements AuditLogger
{
    public function __construct(
        private readonly Database $db,
        private readonly AuthManager $auth,
        private readonly TenantManager $tenant,
    ) {
    }

    public function log(string $action, array $context = []): void
    {
        $this->write($action, null, null, $context);
    }

    public function logChange(string $action, ?array $old, ?array $new, array $context = []): void
    {
        // Reduce to only the keys that actually changed, for a tight diff.
        if ($old !== null && $new !== null) {
            $changedKeys = array_keys(array_merge(
                array_diff_assoc($new, $old),
                array_diff_assoc($old, $new)
            ));
            $old = array_intersect_key($old, array_flip($changedKeys));
            $new = array_intersect_key($new, array_flip($changedKeys));
        }

        $this->write($action, $old, $new, $context);
    }

    private function write(string $action, ?array $old, ?array $new, array $context): void
    {
        $request = app()->has('request') ? request() : null;

        $this->db->table('activity_log')->insert([
            'workspace_id'   => $context['workspace_id'] ?? $this->tenant->id(),
            'user_id'      => $context['user_id'] ?? $this->auth->id(),
            'action'       => $action,
            'subject_type' => $context['subject_type'] ?? null,
            'subject_id'   => $context['subject_id'] ?? null,
            'description'  => $context['description'] ?? null,
            'old_values'   => $old !== null ? $this->encode($old) : null,
            'new_values'   => $new !== null ? $this->encode($new) : null,
            'properties'   => isset($context['properties']) ? $this->encode($context['properties']) : null,
            'ip'           => $request?->ip(),
            'user_agent'   => $request ? substr($request->userAgent(), 0, 255) : null,
            'device'       => $request ? $this->device($request->userAgent()) : null,
            'created_at'   => now(),
        ]);
    }

    private function encode(array $values): string
    {
        return json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * Coarse device classification from the user agent — enough for the audit
     * view without a full UA-parsing dependency.
     */
    private function device(string $userAgent): string
    {
        $ua = strtolower($userAgent);
        if ($ua === '') {
            return 'unknown';
        }
        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            return 'tablet';
        }
        if (str_contains($ua, 'mobi') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
            return 'mobile';
        }
        if (str_contains($ua, 'curl') || str_contains($ua, 'bot') || str_contains($ua, 'http')) {
            return 'api/bot';
        }

        return 'desktop';
    }
}
