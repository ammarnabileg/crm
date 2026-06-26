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
 * The main dashboard. Shows a company overview for tenant users and a platform
 * overview for a super admin who has not selected a company.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = auth()->user();
        $company = tenant()->company();

        if ($company === null && $user->isSuperAdmin()) {
            return $this->view('app.dashboard-platform', [
                'title'         => 'Platform overview',
                'companyCount'  => app('db')->table('companies')->count(),
                'userCount'     => app('db')->table('users')->count(),
                'planCount'     => app('db')->table('plans')->where('is_active', '=', 1)->count(),
                'recent'        => app('db')->table('companies')->orderBy('created_at', 'desc')->limit(5)->get(),
            ]);
        }

        $subscription = $company?->activeSubscription();

        $stats = [
            'members' => Membership::query()->where('status', '=', 'active')->count(),
            'roles'   => Role::withoutTenantScope()->where('company_id', '=', tenant()->id())->count(),
            'plan'    => $subscription?->plan()?->name ?? 'No active plan',
        ];

        $recent = ActivityLog::withoutTenantScope()
            ->where('company_id', '=', tenant()->id())
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get();

        return $this->view('app.dashboard', [
            'title'        => 'Dashboard',
            'company'      => $company,
            'stats'        => $stats,
            'subscription' => $subscription,
            'recent'       => $recent,
        ]);
    }
}
