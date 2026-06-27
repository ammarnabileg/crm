<?php

declare(strict_types=1);

namespace App\Controllers\System;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\System\MaintenanceMode;

/**
 * Dashboard toggle for maintenance mode (Setup Bible: "Maintenance").
 * Everything happens in the browser — no terminal, no flag-file editing by hand.
 */
final class MaintenanceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(can('system.manage'), 403);

        return $this->view('system.maintenance', [
            'title' => 'Maintenance mode',
            'state' => $this->maintenance()->details(),
        ]);
    }

    public function update(Request $request): Response
    {
        abort_unless(can('system.manage'), 403);

        $data = $this->validate($request, [
            'mode'     => 'required|in:enable,disable',
            'message'  => 'nullable|string|max:1000',
            'allow_ip' => 'nullable|string|max:45',
        ]);

        $maintenance = $this->maintenance();

        if (($data['mode'] ?? '') === 'enable') {
            $allowIp = trim((string) ($data['allow_ip'] ?? ''));
            if ($allowIp !== '' && filter_var($allowIp, FILTER_VALIDATE_IP) === false) {
                $this->fail('allow_ip', 'Please enter a valid IP address.', $request->all());
            }

            $maintenance->enable(
                trim((string) ($data['message'] ?? '')),
                $allowIp !== '' ? $allowIp : null
            );
            $this->withSuccess('Maintenance mode is now ON. Only super admins can reach the dashboard.');
        } else {
            $maintenance->disable();
            $this->withSuccess('Maintenance mode is now OFF.');
        }

        return $this->redirect(url('system/maintenance'));
    }

    private function maintenance(): MaintenanceMode
    {
        return new MaintenanceMode();
    }
}
