<?php

declare(strict_types=1);

namespace App\Services\Ats;

use App\Models\Job;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use RuntimeException;

/**
 * Pipeline management (docs/53 ATS): create / clone / reorder pipelines and their
 * stages, and attach a pipeline to a job. `createDefault()` seeds the standard
 * recruitment pipeline so a new job is immediately usable. Tenant-scoped.
 */
final class PipelineManager
{
    /** Standard stages: [name, status_key, stage_type, color, initial, terminal, passed]. */
    private const DEFAULT_STAGES = [
        ['Applied', 'applied', 'sourced', '#94a3b8', 1, 0, 0],
        ['CV Screening', 'in_review', 'screening', '#0ea5e9', 0, 0, 0],
        ['AI Screening', 'in_review', 'assessment', '#6366f1', 0, 0, 0],
        ['HR Interview', 'interviewing', 'interview', '#8b5cf6', 0, 0, 0],
        ['Technical Interview', 'interviewing', 'interview', '#0d9488', 0, 0, 0],
        ['Manager Interview', 'interviewing', 'interview', '#0d9488', 0, 0, 0],
        ['Final Interview', 'interviewing', 'interview', '#0d9488', 0, 0, 0],
        ['Offer', 'offer', 'offer', '#d97706', 0, 0, 0],
        ['Hired', 'hired', 'hired', '#16a34a', 0, 1, 1],
        ['Onboarding', 'hired', 'hired', '#15803d', 0, 0, 1],
        ['Rejected', 'rejected', 'rejected', '#dc2626', 0, 1, 0],
        ['Archived', 'withdrawn', 'rejected', '#6b7280', 0, 1, 0],
    ];

    public function create(string $name, ?int $jobId = null, ?int $userId = null, bool $isTemplate = false): Pipeline
    {
        return Pipeline::create([
            'job_id'      => $jobId,
            'name'        => $name,
            'is_default'  => 0,
            'is_template' => $isTemplate ? 1 : 0,
            'is_active'   => 1,
            'created_by'  => $userId,
        ]);
    }

    /** Create the standard recruitment pipeline for a job and attach it. */
    public function createDefault(int $jobId, ?int $userId = null): Pipeline
    {
        $pipeline = Pipeline::create([
            'job_id'      => $jobId,
            'name'        => 'Default Pipeline',
            'is_default'  => 1,
            'is_template' => 0,
            'is_active'   => 1,
            'created_by'  => $userId,
        ]);
        $pipelineId = (int) $pipeline->getKey();

        $sort = 0;
        foreach (self::DEFAULT_STAGES as [$name, $statusKey, $type, $color, $initial, $terminal, $passed]) {
            $this->addStage($pipelineId, [
                'name'                  => $name,
                'application_status_id' => status_id('application_statuses', $statusKey),
                'stage_type_id'         => lookup_id('pipeline_stage_type', $type),
                'color'                 => $color,
                'is_initial'            => $initial,
                'is_terminal'           => $terminal,
                'is_passed'             => $passed,
            ], $sort++);
        }

        $this->assignToJob($pipelineId, $jobId);

        return $pipeline;
    }

    public function addStage(int $pipelineId, array $attrs, ?int $sortOrder = null): PipelineStage
    {
        if ($sortOrder === null) {
            $max = PipelineStage::query()->where('pipeline_id', '=', $pipelineId)->orderBy('sort_order', 'desc')->first();
            $sortOrder = $max !== null ? ((int) $max['sort_order']) + 1 : 0;
        }
        $attrs['pipeline_id'] = $pipelineId;
        $attrs['sort_order'] = $sortOrder;

        return PipelineStage::create($attrs);
    }

    /** @param int[] $orderedStageIds */
    public function reorder(int $pipelineId, array $orderedStageIds): void
    {
        $order = 0;
        foreach ($orderedStageIds as $stageId) {
            PipelineStage::query()
                ->where('pipeline_id', '=', $pipelineId)
                ->where('id', '=', (int) $stageId)
                ->update(['sort_order' => $order++, 'updated_at' => now()]);
        }
    }

    /** Clone a pipeline (and its stages) onto another job. */
    public function clone(int $pipelineId, ?int $toJobId = null, ?int $userId = null): Pipeline
    {
        $source = Pipeline::find($pipelineId);
        if ($source === null) {
            throw new RuntimeException("Pipeline {$pipelineId} not found in this workspace.");
        }
        $copy = $this->create((string) $source->name . ' (copy)', $toJobId, $userId, (bool) $source->is_template);
        $copyId = (int) $copy->getKey();

        foreach ($source->stages() as $stage) {
            $this->addStage($copyId, [
                'name'                  => (string) $stage['name'],
                'description'           => $stage['description'] ?? null,
                'application_status_id' => $stage['application_status_id'],
                'stage_type_id'         => $stage['stage_type_id'],
                'color'                 => $stage['color'] ?? null,
                'is_initial'            => (int) $stage['is_initial'],
                'is_terminal'           => (int) $stage['is_terminal'],
                'is_passed'             => (int) $stage['is_passed'],
            ], (int) $stage['sort_order']);
        }

        if ($toJobId !== null) {
            $this->assignToJob($copyId, $toJobId);
        }

        return $copy;
    }

    public function assignToJob(int $pipelineId, int $jobId): void
    {
        $job = Job::find($jobId);
        if ($job !== null) {
            $job->update(['pipeline_id' => $pipelineId]);
        }
        Pipeline::query()->where('id', '=', $pipelineId)->update(['job_id' => $jobId, 'updated_at' => now()]);
    }

    /** The initial stage of a job's pipeline, or null. */
    public function initialStage(int $jobId): ?array
    {
        $job = Job::find($jobId);
        if ($job === null || $job->pipeline_id === null) {
            return null;
        }
        $stage = PipelineStage::query()
            ->where('pipeline_id', '=', (int) $job->pipeline_id)
            ->where('is_initial', '=', 1)
            ->orderBy('sort_order')
            ->first();

        return $stage;
    }
}
