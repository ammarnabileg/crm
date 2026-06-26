<?php

declare(strict_types=1);

namespace App\Controllers\App;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\ActivityLog;
use App\Models\Membership;
use App\Models\Role;

/**
 * The main dashboard. Shows a workspace overview for tenant users and a platform
 * overview for a super admin who has not selected a workspace.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = auth()->user();
        $workspace = tenant()->workspace();

        if ($workspace === null && $user->isSuperAdmin()) {
            return $this->view('app.dashboard-platform', [
                'title'         => 'Platform overview',
                'workspaceCount'  => app('db')->table('workspaces')->count(),
                'userCount'     => app('db')->table('users')->count(),
                'planCount'     => app('db')->table('plans')->where('is_active', '=', 1)->count(),
                'recent'        => app('db')->table('workspaces')
                    ->select('workspaces.*', 'workspace_statuses.key AS status')
                    ->join('workspace_statuses', 'workspace_statuses.id', '=', 'workspaces.workspace_status_id')
                    ->orderBy('workspaces.created_at', 'desc')->limit(5)->get(),
            ]);
        }

        $subscription = $workspace?->activeSubscription();

        $stats = [
            'members' => Membership::query()->where('membership_status_id', '=', lookup_id('membership_status', 'active'))->count(),
            'roles'   => Role::withoutTenantScope()->where('workspace_id', '=', tenant()->id())->count(),
            'plan'    => $subscription?->plan()?->name ?? 'No active plan',
        ];

        $recent = ActivityLog::withoutTenantScope()
            ->where('workspace_id', '=', tenant()->id())
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get();

        return $this->view('app.dashboard', [
            'title'        => 'Dashboard',
            'workspace'      => $workspace,
            'stats'        => $stats,
            'subscription' => $subscription,
            'recent'       => $recent,
        ]);
    }
}
