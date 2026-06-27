<?php

declare(strict_types=1);

namespace App\Services\Ats;

use App\Models\Job;
use RuntimeException;

/**
 * Job lifecycle (docs/53 ATS): create → publish → pause → close → archive via
 * `job_statuses`. Each transition stamps the relevant timestamp and dispatches a
 * domain event into the Automation Engine. Tenant-scoped through the Model layer.
 */
final class JobManager
{
    public function create(array $attrs, ?int $userId = null): Job
    {
        $attrs['job_status_id'] ??= status_id('job_statuses', 'draft');
        $attrs['slug'] = $this->uniqueSlug((string) ($attrs['title'] ?? 'job'));
        $attrs['created_by'] = $userId;
        $attrs['updated_by'] = $userId;

        return Job::create($attrs);
    }

    public function publish(int $jobId): Job
    {
        $job = $this->find($jobId);
        $job->update(['job_status_id' => status_id('job_statuses', 'open'), 'published_at' => now()]);
        AtsEvents::dispatch('job.published', ['job_id' => $jobId]);

        return $job;
    }

    public function pause(int $jobId): Job
    {
        $job = $this->find($jobId);
        $job->update(['job_status_id' => status_id('job_statuses', 'paused')]);

        return $job;
    }

    public function close(int $jobId): Job
    {
        $job = $this->find($jobId);
        $job->update(['job_status_id' => status_id('job_statuses', 'closed'), 'closed_at' => now()]);
        AtsEvents::dispatch('job.closed', ['job_id' => $jobId]);

        return $job;
    }

    public function archive(int $jobId): Job
    {
        $job = $this->find($jobId);
        $job->update(['job_status_id' => status_id('job_statuses', 'archived')]);

        return $job;
    }

    private function find(int $jobId): Job
    {
        $job = Job::find($jobId);
        if ($job === null) {
            throw new RuntimeException("Job {$jobId} not found in this workspace.");
        }

        return $job;
    }

    private function uniqueSlug(string $title): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)) ?? '', '-') ?: 'job';
        $slug = $base;
        $n = 2;
        while (Job::withTrashed()->where('slug', '=', $slug)->first() !== null) {
            $slug = $base . '-' . $n;
            $n++;
        }

        return $slug;
    }
}
