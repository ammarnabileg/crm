<?php

declare(strict_types=1);

namespace App\Services\Ats;

use App\Core\Model;
use App\Models\Application;
use App\Models\PipelineStage;
use RuntimeException;

/**
 * Application lifecycle + pipeline movement (docs/53 ATS). Creating an application
 * places it at the pipeline's initial stage; moving it updates the current stage +
 * mirrored status, writes a `status_histories` audit row, and dispatches a domain
 * event. Recruiter/interviewer assignment is supported. Tenant-scoped.
 */
final class ApplicationFlow
{
    public function __construct(private readonly PipelineManager $pipelines = new PipelineManager())
    {
    }

    public function apply(int $jobId, int $userId, array $attrs = []): Application
    {
        $stage = $this->pipelines->initialStage($jobId);
        $statusId = $stage !== null
            ? (int) $stage['application_status_id']
            : (int) status_id('application_statuses', 'applied');

        $application = Application::create(array_merge([
            'job_id'                => $jobId,
            'user_id'               => $userId,
            'current_stage_id'      => $stage !== null ? (int) $stage['id'] : null,
            'application_status_id' => $statusId,
            'applied_at'            => now(),
        ], $attrs));

        AtsEvents::dispatch('application.submitted', [
            'application_id' => (int) $application->getKey(),
            'job_id'         => $jobId,
            'user_id'        => $userId,
        ]);

        return $application;
    }

    public function moveToStage(int $applicationId, int $stageId, ?int $actorId = null, ?string $note = null): Application
    {
        $application = $this->find($applicationId);
        $stage = PipelineStage::find($stageId);
        if ($stage === null) {
            throw new RuntimeException("Pipeline stage {$stageId} not found in this workspace.");
        }

        $fromStatusId = $application->application_status_id !== null ? (int) $application->application_status_id : null;
        $toStatusId = (int) $stage->application_status_id;

        $update = [
            'current_stage_id'      => $stageId,
            'application_status_id' => $toStatusId,
        ];
        if ((bool) $stage->is_terminal) {
            $update['decided_at'] = now();
        }
        $application->update($update);

        $this->recordHistory($application, $fromStatusId, $toStatusId, $actorId, $note ?? ('Moved to ' . (string) $stage->name));

        AtsEvents::dispatch('application.reviewed', [
            'application_id' => $applicationId,
            'stage_id'       => $stageId,
            'stage'          => (string) $stage->name,
        ]);
        if ((bool) $stage->is_passed) {
            AtsEvents::dispatch('candidate.passed', ['application_id' => $applicationId, 'stage' => (string) $stage->name]);
        }

        return $application;
    }

    public function assignRecruiter(int $applicationId, int $recruiterId): Application
    {
        $application = $this->find($applicationId);
        $application->update(['assigned_recruiter_id' => $recruiterId]);

        return $application;
    }

    public function assignInterviewer(int $applicationId, int $interviewerId): Application
    {
        $application = $this->find($applicationId);
        $application->update(['assigned_interviewer_id' => $interviewerId]);

        return $application;
    }

    private function recordHistory(Application $application, ?int $fromStatusId, int $toStatusId, ?int $actorId, string $note): void
    {
        app('db')->table('status_histories')->insert([
            'uuid'           => Model::generateUuid(),
            'workspace_id'   => tenant()->id(),
            'subject_type'   => Application::class,
            'subject_id'     => (int) $application->getKey(),
            'status_type'    => 'application',
            'from_status_id' => $fromStatusId,
            'to_status_id'   => $toStatusId,
            'from_status_key' => $fromStatusId !== null
                ? (string) app('db')->table('application_statuses')->where('id', '=', $fromStatusId)->value('key')
                : null,
            'to_status_key'  => (string) app('db')->table('application_statuses')->where('id', '=', $toStatusId)->value('key'),
            'changed_by'     => $actorId,
            'note'           => $note,
            'created_at'     => now(),
        ]);
    }

    private function find(int $applicationId): Application
    {
        $application = Application::find($applicationId);
        if ($application === null) {
            throw new RuntimeException("Application {$applicationId} not found in this workspace.");
        }

        return $application;
    }
}
