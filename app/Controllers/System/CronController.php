<?php

declare(strict_types=1);

namespace App\Controllers\System;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Scheduling\CronRunner;
use Throwable;

/**
 * No-terminal CRON / queue trigger (Production Readiness Bible).
 *
 * A single tokenized URL that a plain server cron can hit once a minute to drain
 * background jobs and tick the scheduler, so production needs no SSH/CLI:
 *
 *     * * * * * curl -s https://HOST/cron/run?token=YOUR_TOKEN
 *
 * Security model — fail CLOSED:
 *  - The expected token comes from settings('cron.token') or, as a fallback,
 *    env('CRON_TOKEN'). If NO token is configured the endpoint is DISABLED and
 *    returns 404 (it is NEVER an open trigger).
 *  - When a token IS configured, the caller must present it via ?token= or the
 *    X-Cron-Token header; the comparison is constant-time (hash_equals). A
 *    missing/wrong token returns 403.
 *
 * This endpoint is UNAUTHENTICATED (token-gated) and is meant to run OUTSIDE the
 * auth/tenant middleware — it is a system process with no user/tenant. It is a
 * GET and changes no user data via request input, so it is CSRF-exempt. Like the
 * /up probe it should also bypass the maintenance gate so the queue keeps
 * draining during a maintenance window.
 */
final class CronController extends Controller
{
    public function run(Request $request): Response
    {
        $expected = $this->expectedToken();

        // No token configured anywhere → feature disabled, indistinguishable
        // from a non-existent route. Never an open trigger.
        if ($expected === '') {
            return Response::json(['status' => 'not_found'], 404);
        }

        $provided = (string) ($request->query('token')
            ?? $request->header('x-cron-token', ''));

        if (! hash_equals($expected, $provided)) {
            return Response::json(['status' => 'forbidden'], 403);
        }

        try {
            $summary = (new CronRunner())->run();
        } catch (Throwable $e) {
            // A runner blow-up must not leak internals; report a clean 500.
            return Response::json(['status' => 'error'], 500);
        }

        return Response::json(['status' => 'ok'] + $summary, 200);
    }

    /**
     * The configured cron token, or '' when none is set. settings() requires an
     * active tenant and this endpoint runs without one, so a throw there simply
     * falls through to the env fallback.
     */
    private function expectedToken(): string
    {
        $token = '';

        try {
            $token = (string) (settings()->get('cron.token', '') ?? '');
        } catch (Throwable) {
            $token = '';
        }

        if ($token === '') {
            $token = (string) env('CRON_TOKEN', '');
        }

        return $token;
    }
}
