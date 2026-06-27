<?php

declare(strict_types=1);

namespace App\Controllers\Ats;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * Recruiter Workspace (docs/53) — the recruiter's home: today's interviews, pending
 * reviews, new applications, open offers, my tasks and recent activity, all scoped to
 * the current workspace.
 */
final class RecruiterDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $db = app('db');
        $workspaceId = tenant()->id();
        $userId = (int) auth()->id();

        $appsByStatus = static function (string $key) use ($db, $workspaceId): int {
            return $db->table('applications')
                ->where('workspace_id', '=', $workspaceId)
                ->where('application_status_id', '=', status_id('application_statuses', $key))
                ->whereNull('deleted_at')
                ->count();
        };

        return $this->view('ats.dashboard', [
            'title'           => 'Recruiter workspace',
            'openJobs'        => $db->table('jobs')->where('workspace_id', '=', $workspaceId)
                ->where('job_status_id', '=', status_id('job_statuses', 'open'))->whereNull('deleted_at')->count(),
            'newApplications' => $appsByStatus('applied'),
            'pendingReviews'  => $appsByStatus('in_review'),
            'interviewing'    => $appsByStatus('interviewing'),
            'openOffers'      => $db->table('offers')->where('workspace_id', '=', $workspaceId)
                ->where('offer_status_id', '=', status_id('offer_statuses', 'sent'))->whereNull('deleted_at')->count(),
            'todaysInterviews' => $db->table('meetings')
                ->where('workspace_id', '=', $workspaceId)->whereNotNull('interview_id')->whereNull('deleted_at')
                ->orderBy('starts_at')->limit(8)->get(),
            'myTasks'         => $db->table('tasks')
                ->where('workspace_id', '=', $workspaceId)->where('assignee_id', '=', $userId)
                ->where('status', '=', 'open')->whereNull('deleted_at')->orderBy('due_at')->limit(8)->get(),
            'recentJobs'      => $db->table('jobs')->where('workspace_id', '=', $workspaceId)
                ->whereNull('deleted_at')->orderBy('created_at', 'desc')->limit(6)->get(),
        ]);
    }
}
