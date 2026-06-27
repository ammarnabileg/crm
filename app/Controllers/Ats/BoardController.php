<?php

declare(strict_types=1);

namespace App\Controllers\Ats;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Job;
use App\Services\Ats\ApplicationFlow;

/**
 * Kanban pipeline board (docs/53 ATS): a job's applications grouped by pipeline
 * stage, with a server-rendered move action (drag & drop is a progressive JS
 * enhancement on top). Reads require recruitment.view; move requires
 * recruitment.manage.
 */
final class BoardController extends Controller
{
    /**
     * Hard cap on applications loaded into the board in one render. A Kanban view of
     * 100k applications would be useless and memory-heavy; beyond the cap the user is
     * told to filter (no silent truncation). Scoped, indexed and ordered so the DB
     * returns only the most recent slice.
     */
    private const BOARD_CAP = 500;

    public function show(Request $request): Response
    {
        $jobId = (int) $request->query('job', 0);
        $job = Job::find($jobId);
        abort_unless($job !== null, 404, 'Job not found.');

        $db = app('db');
        $workspaceId = tenant()->id();

        $stages = $db->table('pipeline_stages')
            ->where('pipeline_id', '=', (int) $job->pipeline_id)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->get();

        // Fetch one more than the cap so we can detect (and disclose) truncation.
        $rows = $db->table('applications')
            ->select('applications.*', 'users.name AS candidate_name', 'users.email AS candidate_email')
            ->leftJoin('users', 'users.id', '=', 'applications.user_id')
            ->where('applications.workspace_id', '=', $workspaceId)
            ->where('applications.job_id', '=', $jobId)
            ->whereNull('applications.deleted_at')
            ->orderBy('applications.applied_at', 'desc')
            ->limit(self::BOARD_CAP + 1)
            ->get();

        $truncated = count($rows) > self::BOARD_CAP;
        if ($truncated) {
            $rows = array_slice($rows, 0, self::BOARD_CAP);
        }

        // Group applications by their current stage.
        $byStage = [];
        foreach ($rows as $row) {
            $byStage[(int) ($row['current_stage_id'] ?? 0)][] = $row;
        }

        return $this->view('ats.board', [
            'title'     => 'Pipeline — ' . (string) $job->title,
            'job'       => $job,
            'stages'    => $stages,
            'byStage'   => $byStage,
            'truncated' => $truncated,
            'cap'       => self::BOARD_CAP,
        ]);
    }

    public function move(Request $request): Response
    {
        $applicationId = (int) $request->input('application_id');
        $stageId = (int) $request->input('stage_id');
        $jobId = (int) $request->input('job_id');

        (new ApplicationFlow())->moveToStage($applicationId, $stageId, (int) auth()->id());
        $this->withSuccess('Candidate moved.');

        return $this->redirect(url('jobs/board?job=' . $jobId));
    }
}
