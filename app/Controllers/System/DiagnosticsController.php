<?php

declare(strict_types=1);

namespace App\Controllers\System;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\System\SystemDiagnostics;

/**
 * Renders the read-only System Diagnostics / Health Check report. Viewable any
 * time from the dashboard by a super-admin; never mutates anything.
 */
final class DiagnosticsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(can('system.manage'), 403);

        $diagnostics = new SystemDiagnostics();
        $groups = $diagnostics->run();
        $summary = $diagnostics->summarize($groups);

        return $this->view('system.diagnostics', [
            'title'     => 'System Diagnostics',
            'groups'    => $groups,
            'summary'   => $summary,
            'generated' => now(),
        ]);
    }
}
