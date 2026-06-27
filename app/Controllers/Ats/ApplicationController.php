<?php

declare(strict_types=1);

namespace App\Controllers\Ats;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Application;
use App\Services\Ats\CandidateTimeline;

/**
 * Application detail (docs/53 ATS): the candidate's application with its full
 * timeline (applications, status changes, interviews/meetings, notes, offers).
 * Requires recruitment.view.
 */
final class ApplicationController extends Controller
{
    public function show(Request $request): Response
    {
        $id = (int) $request->query('id', 0);
        $application = Application::find($id);
        abort_unless($application !== null, 404, 'Application not found.');

        $db = app('db');
        $candidate = $db->table('users')->where('id', '=', (int) $application->user_id)->first();
        $job = $db->table('jobs')->where('id', '=', (int) $application->job_id)->value('title');
        $timeline = (new CandidateTimeline())->forApplication($id);

        return $this->view('ats.application', [
            'title'       => 'Application',
            'application' => $application,
            'candidate'   => $candidate,
            'jobTitle'    => (string) $job,
            'status'      => $application->statusKey(),
            'timeline'    => $timeline,
        ]);
    }
}
