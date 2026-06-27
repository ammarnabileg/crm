<?php

declare(strict_types=1);

namespace App\Controllers\System;

use App\Core\Controller;
use App\Core\Response;
use Throwable;

/**
 * Liveness probe for load balancers / uptime monitors (Production Readiness Bible).
 * Unauthenticated and deliberately minimal — it confirms the app boots and the
 * database answers, and returns NO internal detail. It bypasses the maintenance
 * gate so an orchestrator does not kill a healthy container during maintenance.
 * The full, gated health report lives at /system/diagnostics.
 */
final class HealthController extends Controller
{
    public function up(): Response
    {
        try {
            $ok = (int) app('db')->scalar('SELECT 1') === 1;
        } catch (Throwable) {
            $ok = false;
        }

        return Response::json(['status' => $ok ? 'ok' : 'error'], $ok ? 200 : 503);
    }
}
