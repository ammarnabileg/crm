<?php

declare(strict_types=1);

namespace App\Controllers\Ats;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Ats\JobManager;
use App\Services\Ats\PipelineManager;

/**
 * Jobs (docs/53 ATS): list jobs, create a job (with the default pipeline), and run
 * lifecycle actions (publish/close/archive). Reads require recruitment.view; writes
 * recruitment.manage (enforced at the route layer).
 */
final class JobController extends Controller
{
    public function index(Request $request): Response
    {
        $db = app('db');
        $workspaceId = tenant()->id();

        $jobs = $db->table('jobs')
            ->select('jobs.*', 'job_statuses.key AS status')
            ->join('job_statuses', 'job_statuses.id', '=', 'jobs.job_status_id')
            ->where('jobs.workspace_id', '=', $workspaceId)
            ->whereNull('jobs.deleted_at')
            ->orderBy('jobs.created_at', 'desc')
            ->limit(100)
            ->get();

        // Application counts per job (one grouped pass).
        $counts = [];
        foreach ($jobs as $job) {
            $counts[(int) $job['id']] = $db->table('applications')
                ->where('workspace_id', '=', $workspaceId)
                ->where('job_id', '=', (int) $job['id'])
                ->whereNull('deleted_at')
                ->count();
        }

        return $this->view('ats.jobs', [
            'title'  => 'Jobs',
            'jobs'   => $jobs,
            'counts' => $counts,
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, ['title' => 'required|max:160']);

        $job = (new JobManager())->create(['title' => $data['title']], (int) auth()->id());
        (new PipelineManager())->createDefault((int) $job->getKey(), (int) auth()->id());

        $this->withSuccess('Job created with a default pipeline.');

        return $this->redirect(url('jobs/board?job=' . $job->getKey()));
    }

    public function publish(Request $request): Response
    {
        (new JobManager())->publish((int) $request->input('id'));
        $this->withSuccess('Job published.');

        return $this->redirect(url('jobs'));
    }

    public function close(Request $request): Response
    {
        (new JobManager())->close((int) $request->input('id'));
        $this->withSuccess('Job closed.');

        return $this->redirect(url('jobs'));
    }

    public function archive(Request $request): Response
    {
        (new JobManager())->archive((int) $request->input('id'));
        $this->withSuccess('Job archived.');

        return $this->redirect(url('jobs'));
    }
}
