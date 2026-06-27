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

        // Application counts for all listed jobs in ONE grouped query (no N+1).
        $counts = [];
        $jobIds = array_map(static fn (array $j): int => (int) $j['id'], $jobs);
        if ($jobIds !== []) {
            $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
            $rows = $db->select(
                "SELECT job_id, COUNT(*) AS total FROM applications
                 WHERE workspace_id = ? AND job_id IN ({$placeholders}) AND deleted_at IS NULL
                 GROUP BY job_id",
                array_merge([$workspaceId], $jobIds)
            );
            foreach ($rows as $row) {
                $counts[(int) $row['job_id']] = (int) $row['total'];
            }
        }
        // Jobs with no applications still need a 0 so the view never misses a key.
        foreach ($jobIds as $id) {
            $counts[$id] ??= 0;
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
